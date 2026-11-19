<?php
/**
 * @package    cccsocialmedia
 *
 * @author     COOLCAT creations - Elisa Foltyn <mail@coolcat-creations.com>
 * @copyright  COOLCAT creations - Elisa Foltyn
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://coolcat-creations.com
 */

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * CCC Socialmedia plugin.
 *
 * @package  CCC socialmedia
 * @since    1.0
 */
class plgSystemCccsocialmedia extends CMSPlugin
{
	public const GOOGLE    = 'google';
	public const FACEBOOK  = 'facebook';
	public const PINTEREST = 'pinterest';
	public const NONE      = '';

	/**
	 * Application object
	 *
	 * The application is injected by parent constructor
	 *
	 * @var    CMSApplication
	 * @since  1.0
	 */
	protected $app;

	/**
	 * Database object
	 *
	 * The database is injected by parent constructor
	 *
	 * @var    DatabaseDriver|\JDatabaseDriver
	 * @since  1.0
	 */
	protected $db;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Add fields for the OpenGraph data to the form
	 *
	 * @param   \Joomla\CMS\Form\Form  $form
	 *
	 * @return boolean
	 * @since  1.0
	 */
	public function onContentPrepareForm(Form $form): bool
	{
		$app    = $this->getApp();
		$option = $app->getInput()->get('option');
		$client = $app->getName();

		$whereToRun = $this->params->get('whereToRun', 'both');
		if (($whereToRun === 'administrator' || $whereToRun === 'site')
			&& $client !== $whereToRun)
		{
			return true;
		}

		switch ("$option.$client")
		{
			case 'com_menus.administrator':
			{
				$form::addFormPath(__DIR__ . '/forms');
				$form->loadFile('cccsocialmedia_menu', false);

				break;
			}

			case 'com_content.administrator':
			case 'com_content.site':
			{
				$form::addFormPath(__DIR__ . '/forms');
				$form->loadFile('cccsocialmedia_article', false);

				break;
			}
		}

		return true;
	}

	/**
	 * Prepare the OpenGraph metadata for rendeering
	 *
	 * @return bool
	 * @since  1.0
	 */
	public function onBeforeCompileHead(): bool
	{
		$app = $this->getApp();

		if (!$app->isClient('site'))
		{
			return true;
		}

		$input  = $app->getInput();
		$option = $input->get('option', '', 'cmd');
		$view   = $input->get('view', '', 'cmd');

		if (($option . '.' . $view) === 'com_finder.indexer')
		{
			return true;
		}

		if ($input->get('format', '', 'cmd') === 'feed')
		{
			return true;
		}

		/** @var \Joomla\CMS\Document\Document $document */
		$document = $app->getDocument();

		// Only a single article view of com_content carries article data. Any other "id" in the
		// request (categories, contacts, other components) must not load an article.
		$article = null;

		if ($option === 'com_content' && $view === 'article' && !is_array($input->get('id', 0, 'raw')))
		{
			$article = $this->getArticle($input->getInt('id', 0));
		}

		$menuParams     = $this->getMenuParams();
		$articleAttribs = new Registry($article->attribs ?? '{}');
		$articleImages  = new Registry($article->images ?? '{}');
		$userAgent      = $this->getBot();

		$this->combineSettings($this->params, $articleAttribs, $menuParams);
		$this->addImageFallback($this->params, $articleImages, $view, $userAgent);
		$this->addTitleFallback($this->params, $articleAttribs, $menuParams, $document);
		$this->addDescriptionFallback($this->params, $menuParams, $document, $view);

		if ($article !== null)
		{
			$this->addPublishedFallback($this->params, $article);
		}

		$this->injectOpenGraphData($document, $this->params);

		return true;
	}

	/**
	 * @param   \Joomla\Registry\Registry  $params
	 * @param   \Joomla\Registry\Registry  $articleAttribs
	 * @param   \Joomla\Registry\Registry  $menuParams
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function combineSettings(Registry $params, Registry $articleAttribs, Registry $menuParams): void
	{
		static $settings = array(
			'og_title',
			'og_type',
			'og_description',
			'og_image',
			'og_image_fb',
			'og_image_pi',
			'og_image_alt',
			'og_article_published_time',
			'og_article_author',
			'og_product_availability',
			'og_product_price_currency',
			'og_product_price_amount',
			'tw_title',
			'tw_description',
			'tw_image',
			'tw_image_alt',
			'tw_site',
			'tw_type',
			'tw_creator'
		);

		$params->set('sitename', $this->getApp()->get('sitename'));
		$params->set('base_url', Uri::base());
		// Canonical URL without the query string, so tracking or arbitrary parameters do not end up in og:url
		$params->set('url', Uri::current());

		// "menu" (default, behaviour of all earlier versions): menu item before article.
		// "article": article before menu item. Plugin parameters are always the last fallback.
		[$first, $second] = $params->get('priority', 'menu') === 'article'
			? [$articleAttribs, $menuParams]
			: [$menuParams, $articleAttribs];

		foreach ($settings as $setting)
		{
			$params->set(
				$setting,
				$first->get(
					$setting,
					$second->get(
						$setting,
						$params->get(
							$setting,
							''
						)
					)
				)
			);
		}
	}

	/**
	 * @param   \Joomla\Registry\Registry  $params
	 * @param   \Joomla\Registry\Registry  $articleImages
	 * @param   string                     $view
	 * @param   string                     $userAgent
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function addImageFallback(Registry $params, Registry $articleImages, string $view, string $userAgent): void
	{

		$alt = $params->get('og_image_alt');

		if ($userAgent === self::FACEBOOK && $params->get('og_image_fb') > '')
		{
			$image = $params->get('og_image_fb');
		}
		elseif ($userAgent === self::GOOGLE && $params->get('og_image') > '')
		{
			$image = $params->get('og_image');
		}
		elseif ($userAgent === self::PINTEREST && $params->get('og_image_pi') > '')
		{
			$image = $params->get('og_image_pi');
		}
		elseif ($params->get('og_image_fb') > '')
		{
			$image = $params->get('og_image_fb');
		}
		else
		{
			[$articleImage, $articleAlt] = $this->getArticleImage($articleImages);

			if ($articleImage > '')
			{
				[$image, $alt] = [$articleImage, $articleAlt];
			}
			else
			{
				// Last fallback: the general image, so pages without an own image still get one
				$image = (string) $params->get('og_image', '');
			}
		}

		$params->set('og_image', $image);
		$params->set('og_image_alt', $alt);
		$params->set('tw_image', $image);
		$params->set('tw_image_alt', $alt);
	}

	/**
	 * @param   \Joomla\Registry\Registry      $params
	 * @param   \Joomla\Registry\Registry      $articleAttribs
	 * @param   \Joomla\Registry\Registry      $menuParams
	 * @param   \Joomla\CMS\Document\Document  $document
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function addTitleFallback(
		Registry $params,
		Registry $articleAttribs,
		Registry $menuParams,
		Document $document
	): void {
		$title = $this->getTitle($articleAttribs, $menuParams, $document);

		$params->def('og_title', $title);
		$params->def('tw_title', $title);
	}

	/**
	 * @param   \Joomla\Registry\Registry       $params
	 * @param   \Joomla\Registry\Registry       $menuParams
	 * @param   \Joomla\CMS\Document\Document   $document
	 * @param                                   $view
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function addDescriptionFallback(Registry $params, Registry $menuParams, Document $document, $view): void
	{
		$description = $this->getDescription($menuParams, $document, $view);

		$params->def('og_description', $description);
		$params->def('tw_description', $description);
	}

	/**
	 * @param   \Joomla\Registry\Registry  $params
	 * @param   \Joomla\CMS\Table\Content  $article
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function addPublishedFallback(Registry $params, Content $article): void
	{
		$created = $article->publish_up ?: ($article->created ?? '');

		// Open Graph expects ISO 8601
		$params->def('og_article_published_time', $created ? Factory::getDate($created)->toISO8601() : '');
	}

	/**
	 * Get the article with the given ID, but only if the current visitor may see it
	 *
	 * @param   int  $id
	 *
	 * @return \Joomla\CMS\Table\Content|null  Null if the article does not exist, is not published or not accessible
	 * @since  2.0.0
	 */
	private function getArticle(int $id): ?Content
	{
		if ($id <= 0)
		{
			return null;
		}

		$article = new Content($this->getDb());

		if (!$article->load($id) || (int) $article->state !== 1)
		{
			return null;
		}

		$user = $this->getApp()->getIdentity();

		if ($user === null || !\in_array((int) $article->access, array_map('intval', $user->getAuthorisedViewLevels()), true))
		{
			return null;
		}

		$now = Factory::getDate()->toUnix();

		if (!empty($article->publish_up) && Factory::getDate($article->publish_up)->toUnix() > $now)
		{
			return null;
		}

		if (!empty($article->publish_down) && Factory::getDate($article->publish_down)->toUnix() <= $now)
		{
			return null;
		}

		return $article;
	}

	/**
	 * The application, without relying on the deprecated property injection of CMSPlugin
	 *
	 * @return \Joomla\CMS\Application\CMSApplication
	 * @since  2.1.0
	 */
	private function getApp()
	{
		if ($this->app !== null)
		{
			return $this->app;
		}

		if (method_exists($this, 'getApplication') && $this->getApplication() !== null)
		{
			return $this->getApplication();
		}

		return Factory::getApplication();
	}

	/**
	 * The database, without relying on the deprecated property injection of CMSPlugin
	 *
	 * @return \Joomla\Database\DatabaseInterface
	 * @since  2.1.0
	 */
	private function getDb()
	{
		return $this->db ?? Factory::getContainer()->get(DatabaseInterface::class);
	}

	/**
	 * @param   \Joomla\Registry\Registry  $articleImages
	 *
	 * @return array
	 * @since  2.0.0
	 */
	private function getArticleImage(Registry $articleImages): array
	{
		$image = $alt = '';

		if ($articleImages->get('image_intro') > '') {
			// get part before # in image if # exists
			if (strpos($articleImages->get('image_intro'), '#') !== false) {
				$image = substr($articleImages->get('image_intro'), 0, strpos($articleImages->get('image_intro'), '#'));
			} else {
				$image = $articleImages->get('image_intro');
			}

			$alt = $articleImages->get('image_intro_alt', '');

		}
		if ($articleImages->get('image_fulltext') > '') {

			if (strpos($articleImages->get('image_fulltext'), '#') !== false) {
				$image = substr($articleImages->get('image_fulltext'), 0, strpos($articleImages->get('image_fulltext'), '#'));
			} else {
				$image = $articleImages->get('image_fulltext');
			}

			$alt = $articleImages->get('image_fulltext_alt', '');

		}

		return array($image, $alt);
	}

	/**
	 * @param   \Joomla\Registry\Registry      $articleAttribs
	 * @param   \Joomla\Registry\Registry      $menuParams
	 * @param   \Joomla\CMS\Document\Document  $document
	 *
	 * @return string
	 * @since  2.0.0
	 */
	private function getTitle(Registry $articleAttribs, Registry $menuParams, Document $document): string
	{
		return $articleAttribs->get(
			'article_page_title',
			$menuParams->get(
				'page_title',
				$document->title ?? ''
			)
		);
	}

	/**
	 * @param   \Joomla\Registry\Registry      $menuParams
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param                                  $view
	 *
	 * @return string
	 * @since  2.0.0
	 */
	private function getDescription(Registry $menuParams, Document $document, $view): string
	{
		if ($view === 'category')
		{
			$description = $menuParams->get(
				'menu-meta_description',
				$menuParams->get(
					'page_description',
					$document->description ?? ''
				)
			);
		}
		else
		{
			$description = $document->description ?? '';
		}

		return $description;
	}

	/**
	 * Get the bot, if any
	 *
	 * If the bot is self::FACEBOOK, gzip compression is turned off.
	 *
	 * @return string One of this class' constants
	 * @since  2.0.0
	 */
	private function getBot(): string
	{
		static $userAgents = [
			self::GOOGLE    => ['googlebot'],
			self::FACEBOOK  => ['facebot', 'facebook', 'LinkedInBot'],
			self::PINTEREST => ['pinterest'],
		];

		$bot       = self::NONE;
		$app       = $this->getApp();
		$useragent = $app->client->userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');

		if (!empty($useragent))
		{
			foreach ($userAgents as $key => $subStrings)
			{
				foreach ($subStrings as $subString)
				{
					if (stripos($useragent, $subString) !== false)
					{
						$bot = $key;
						break 2;
					}
				}
			}

			if ($bot === self::FACEBOOK)
			{
				$app->set('gzip', 0);
			}
		}

		return $bot;
	}

	/**
	 * Get the parameters associated with the active menu item
	 *
	 * @return \Joomla\Registry\Registry
	 * @since  2.0.0
	 */
	private function getMenuParams(): Registry
	{
		$menu   = $this->getApp()->getMenu();

		if ($menu === null)
		{
			return new Registry([]);
		}

		$active = $menu->getActive();

		if ($active === null)
		{
			return new Registry([]);
		}

		$itemId = $active->id;

		return $menu->getParams($itemId);
	}

	/**
	 * Add OpenGraph data to document
	 *
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   string|null                    $image
	 * @param   string|null                    $alt
	 * @param   string|null                    $baseUrl
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function setOpenGraphImage(
		Document $document,
		?string $image = '',
		?string $alt = '',
		?string $baseUrl = ''
	): void {
		if (empty($image) || !empty($document->getMetaData('og:image')))
		{
			return;
		}

		$image = ltrim(preg_replace('~^([\w\-./\\\]+).*$~', '$1', $image), '/');

		// Resolve the file inside the site root only, never outside of it
		$root = realpath(\defined('JPATH_ROOT') ? JPATH_ROOT : getcwd());
		$file = realpath($root . '/' . $image);

		if ($root === false || $file === false || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file))
		{
			return;
		}

		$url = empty($baseUrl) ? '' : rtrim($baseUrl, '/') . '/';
		$url .= $image;

		$this->setMetaDataIfNotSet($document, 'twitter:image', $url, 'name');
		$this->setMetaDataIfNotSet($document, 'twitter:image:alt', $alt, 'name');

		// Another extension already set an og:image: keep it, and do not add type or size of a different image
		if (!empty($document->getMetaData('og:image', 'property')))
		{
			return;
		}

		$this->setMetaData($document, 'og:image', $url, 'property');
		$this->setMetaData($document, 'og:image:secure_url', $url, 'property');
		$this->setMetaData($document, 'og:image:alt', $alt, 'property');

		$info = getimagesize($file);

		if (\is_array($info))
		{
			$this->setMetaData($document, 'og:image:type', $info['mime'], 'property');
			$this->setMetaData($document, 'og:image:height', (string) $info[1], 'property');
			$this->setMetaData($document, 'og:image:width', (string) $info[0], 'property');
		}
	}

	/**
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   string|null                    $type
	 * @param   string|null                    $price
	 * @param   string|null                    $currency
	 * @param   string|null                    $availability
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function setProduct(
		Document $document,
		?string $type = '',
		?string $price = '',
		?string $currency = '',
		?string $availability = ''
	): void {
		if ($type === 'product')
		{
			$this->setMetaDataIfNotSet($document, 'product:price:amount', $price, 'property');
			$this->setMetaDataIfNotSet($document, 'product:price:currency', $currency, 'property');
			$this->setMetaDataIfNotSet($document, 'og:availability', $availability, 'property');
		}
	}

	/**
	 * Set metadata in document only if not already set
	 *
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   string                         $key
	 * @param   string|null                    $value
	 * @param   string                         $attribute
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function setMetaDataIfNotSet(
		Document $document,
		string $key,
		?string $value = '',
		string $attribute = 'name'
	): void {
		if (!empty($value) && empty($document->getMetaData($key, $attribute)))
		{
			$this->setMetaData($document, $key, $value, $attribute);
		}
	}

	/**
	 * Set metadata in document regardless previous content
	 *
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   string                         $key
	 * @param   string|null                    $value
	 * @param   string                         $attribute
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function setMetaData(Document $document, string $key, ?string $value = '', string $attribute = 'name'): void
	{
		$document->setMetaData($key, $value, $attribute);
	}

	/**
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   \Joomla\Registry\Registry      $params
	 *
	 * @return void
	 * @since  2.0.0
	 */
	private function injectOpenGraphData(Document $document, Registry $params): void
	{
		$this->setMetaData($document, 'og:url', $params->get('url'), 'property');

		$this->setMetaData($document, 'og:site_name', $params->get('sitename'), 'property');
		$this->setMetaDataIfNotSet($document, 'twitter:site', $params->get('tw_site'), 'name');

		$this->setMetaDataIfNotSet($document, 'og:type', $params->get('og_type'), 'property');
		$this->setMetaDataIfNotSet($document, 'twitter:card', $params->get('tw_type'), 'name');

		$this->setMetaDataIfNotSet($document, 'og:title', $params->get('og_title'), 'property');
		$this->setMetaDataIfNotSet($document, 'twitter:title', $params->get('tw_title'), 'name');

		$this->setMetaDataIfNotSet($document, 'og:description', $params->get('og_description'), 'property');
		$this->setMetaDataIfNotSet($document, 'twitter:description', $params->get('tw_description'), 'name');

		$this->setMetaDataIfNotSet($document, 'article:author', $params->get('og_article_author'), 'property');
		$this->setMetaDataIfNotSet($document, 'twitter:creator', $params->get('tw_creator'), 'name');

		$this->setMetaDataIfNotSet(
			$document,
			'article:published_time',
			$params->get('og_article_published_time'),
			'property'
		);

		$this->setOpenGraphImage(
			$document,
			$params->get('og_image'),
			$params->get('og_image_alt'),
			$params->get('base_url')
		);

		$this->setProduct(
			$document,
			$params->get('og_type'),
			$params->get('og_product_price_amount'),
			$params->get('og_product_price_currency'),
			$params->get('og_product_availability')
		);
	}
}
