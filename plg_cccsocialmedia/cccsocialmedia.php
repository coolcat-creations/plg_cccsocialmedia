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
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\EventInterface;
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
	private const GOOGLE    = 'google';
	private const FACEBOOK  = 'facebook';
	private const PINTEREST = 'pinterest';
	private const NONE      = '';

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
	 * @var    DatabaseDriver
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
	 * @param   EventInterface  $event  The event
	 *
	 * @return boolean
	 * @since  1.0
	 */
	public function onContentPrepareForm(EventInterface $event): bool
	{
		/** @var \Joomla\CMS\Form\Form $form */
		$form = $event->getArgument('0');

		$option = $this->app->input->get('option');
		$client = $this->app->getName();

		switch ("$option.$client")
		{
			case 'com_menus.admin':
			{
				$form::addFormPath(__DIR__ . '/forms');
				$form->loadFile('cccsocialmedia_menu', false);

				break;
			}

			case 'com_content.admin':
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
	 * Clean attributes before getting saved
	 *
	 * @param   EventInterface  $event
	 *
	 * @return  boolean
	 * @since  1.0
	 */
	public function onContentBeforeSave(EventInterface $event): bool
	{
		$table = $event->getArgument('1');

		if (!($table instanceof Content))
		{
			return true;
		}

		if ($this->app->input->get('option') === 'com_content')
		{
			$attribs        = json_decode($table->attribs, true);
			$table->attribs = json_encode($attribs);
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
		$input  = $this->app->input;
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

		if (!$this->app->isClient('site'))
		{
			return true;
		}

		$document       = $this->app->getDocument();
		$article        = $this->getArticle($input->get('id', 0, 'int'));
		$menuParams     = $this->getMenuParams();
		$articleAttribs = new Registry($article->attribs ?? '{}');
		$articleImages  = new Registry($article->images ?? '{}');

		$this->combineSettings($this->params, $articleAttribs, $menuParams);
		$this->addImageFallback($this->params, $articleImages, $view);
		$this->addTitleFallback($this->params, $articleAttribs, $menuParams, $document);
		$this->addDescriptionFallback($this->params, $menuParams, $document, $view);
		$this->addPublishedFallback($this->params, $article);

		$this->injectTwitterData($document, $this->params);
		$this->injectOpenGraphData($document, $this->params);

		return true;
	}

	/**
	 * @param   \Joomla\Registry\Registry  $params
	 * @param   \Joomla\Registry\Registry  $articleAttribs
	 * @param   \Joomla\Registry\Registry  $menuParams
	 *
	 * @return void
	 * @since  __DEPLOY_VERSION__
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

		$params->set('sitename', $this->app->getConfig()->get('sitename'));
		$params->set('base_url', Uri::base());
		$params->set('url', Uri::getInstance());

		foreach ($settings as $setting)
		{
			$params->set(
				$setting,
				// Priority A - Menu parameters
				$menuParams->get(
					$setting,
					// Priority B - Article parameters
					$articleAttribs->get(
						$setting,
						// Priority C - Module parameters
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
	 *
	 * @return void
	 * @since  __DEPLOY_VERSION__
	 */
	private function addImageFallback(Registry $params, Registry $articleImages, string $view): void
	{
		$userAgent = $this->getBot();

		if ($view !== 'article')
		{
			return;
		}

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
			[$image, $alt] = $this->getArticleImage($articleImages);
		}

		$params->def('og_image', $image);
		$params->def('og_image_alt', $alt);
		$params->def('tw_image', $image);
		$params->def('tw_image_alt', $alt);
	}

	/**
	 * @param   \Joomla\Registry\Registry      $params
	 * @param   \Joomla\Registry\Registry      $articleAttribs
	 * @param   \Joomla\Registry\Registry      $menuParams
	 * @param   \Joomla\CMS\Document\Document  $document
	 *
	 * @return void
	 * @since  __DEPLOY_VERSION__
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
	 * @since  __DEPLOY_VERSION__
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
	 * @since  __DEPLOY_VERSION__
	 */
	private function addPublishedFallback(Registry $params, Content $article): void
	{
		$params->def('og_article_published_time', $article->created ?? '');
	}

	/**
	 * Get the article with the given ID
	 *
	 * @param   int  $id
	 *
	 * @return \Joomla\CMS\Table\Content
	 * @since  __DEPLOY_VERSION__
	 */
	private function getArticle(int $id): Content
	{
		$article = new Content($this->db);
		$article->load($id);

		return $article;
	}

	/**
	 * @param   \Joomla\Registry\Registry  $articleImages
	 *
	 * @return array
	 * @since  __DEPLOY_VERSION__
	 */
	private function getArticleImage(Registry $articleImages): array
	{
		$image = $alt = '';

		if ($articleImages->get('image_intro') > '')
		{
			$image = $articleImages->get('image_intro', '');
			$alt   = $articleImages->get('image_intro_alt', '');
		}
		elseif ($articleImages->get('image_fulltext') > '')
		{
			$image = $articleImages->get('image_fulltext', '');
			$alt   = $articleImages->get('image_fulltext_alt', '');
		}

		return array($image, $alt);
	}

	/**
	 * @param   \Joomla\Registry\Registry      $articleAttribs
	 * @param   \Joomla\Registry\Registry      $menuParams
	 * @param   \Joomla\CMS\Document\Document  $document
	 *
	 * @return string
	 * @since  __DEPLOY_VERSION__
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
	 * @since  __DEPLOY_VERSION__
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
	 * @since  __DEPLOY_VERSION__
	 */
	private function getBot(): string
	{
		static $userAgents = [
			self::GOOGLE    => ['googlebot'],
			self::FACEBOOK  => ['facebot', 'facebook', 'LinkedInBot'],
			self::PINTEREST => ['pinterest'],
		];

		$bot       = self::NONE;
		$useragent = $this->app->client->userAgent;

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
				$this->app->set('gzip', 0);
			}
		}

		return $bot;
	}

	/**
	 * Get the parameters associated with the active menu item
	 *
	 * @return \Joomla\Registry\Registry
	 * @since  __DEPLOY_VERSION__
	 */
	private function getMenuParams(): Registry
	{
		$menu   = $this->app->getMenu();
		$active = $menu->getActive();

		if ($active === null)
		{
			return new Joomla\Registry\Registry([]);
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
	 * @since  __DEPLOY_VERSION__
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

		$image = preg_replace('~^([\w\-./\\\]+).*$~', '$1', $image);
		$url   = $baseUrl . $image;

		$this->setMetaData($document, 'og:image', $url, 'property');
		$this->setMetaData($document, 'og:image:secure_url', $url, 'property');
		$this->setMetaData($document, 'og:image:alt', $alt, 'property');

		$info = getimagesize($image);

		if (\is_array($info))
		{
			$this->setMetaData($document, 'og:image:type', $info['mime'], 'property');
			$this->setMetaData($document, 'og:image:height', $info[1]);
			$this->setMetaData($document, 'og:image:width', $info[0]);
		}
	}

	/**
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   string|null                    $image
	 * @param   string|null                    $alt
	 * @param   string|null                    $baseUrl
	 *
	 * @return void
	 * @since  __DEPLOY_VERSION__
	 */
	private function setTwitterImage(
		Document $document,
		?string $image = '',
		?string $alt = '',
		?string $baseUrl = ''
	): void {
		if (empty($image) || !empty($document->getMetaData('twitter:image')))
		{
			return;
		}

		$this->setMetaDataIfNotSet($document, 'twitter:image', $baseUrl . $image, 'name');
		$this->setMetaDataIfNotSet($document, 'twitter:image:alt', $alt, 'name');
	}

	/**
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   string|null                    $type
	 * @param   string|null                    $price
	 * @param   string|null                    $currency
	 * @param   string|null                    $availability
	 *
	 * @return void
	 * @since  __DEPLOY_VERSION__
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
	 * @since  __DEPLOY_VERSION__
	 */
	private function setMetaDataIfNotSet(
		Document $document,
		string $key,
		?string $value = '',
		string $attribute = 'name'
	): void {
		if (!empty($value) && empty($document->getMetaData($key)))
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
	 * @since  __DEPLOY_VERSION__
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
	 * @since  __DEPLOY_VERSION__
	 */
	private function injectTwitterData(Document $document, Registry $params): void
	{
		$this->setMetaDataIfNotSet($document, 'twitter:card', $params->get('tw_type'), 'name');
		$this->setMetaDataIfNotSet($document, 'twitter:site', $params->get('tw_site'), 'name');
		$this->setMetaDataIfNotSet($document, 'twitter:creator', $params->get('tw_creator'), 'name');
		$this->setMetaDataIfNotSet($document, 'twitter:title', $params->get('tw_title'), 'name');
		$this->setMetaDataIfNotSet($document, 'twitter:description', $params->get('tw_description'), 'name');
		$this->setTwitterImage(
			$document,
			$params->get('tw_image'),
			$params->get('tw_image_alt'),
			$params->get('base_url')
		);
	}

	/**
	 * @param   \Joomla\CMS\Document\Document  $document
	 * @param   \Joomla\Registry\Registry      $params
	 *
	 * @return void
	 * @since  __DEPLOY_VERSION__
	 */
	private function injectOpenGraphData(Document $document, Registry $params): void
	{
		$this->setMetaDataIfNotSet($document, 'og:type', $params->get('og_type'), 'property');
		$this->setMetaData($document, 'og:site_name', $params->get('sitename'), 'property');
		$this->setMetaData($document, 'og:url', $params->get('url'), 'property');
		$this->setMetaDataIfNotSet($document, 'og:title', $params->get('og_title'), 'property');
		$this->setMetaDataIfNotSet($document, 'og:description', $params->get('og_description'), 'property');
		$this->setMetaDataIfNotSet(
			$document,
			'article:published_time',
			$params->get('og_article_published_time'),
			'property'
		);
		$this->setMetaDataIfNotSet($document, 'article:author', $params->get('og_article_author'), 'property');
		$this->setOpenGraphImage(
			$document,
			$params->get('og_image'),
			$params->get('og_image_alt'),
			$params->get('base_url')
		);
		$this->setProduct(
			$document,
			$params->get('og_type'),
			$params->get('og_product_price'),
			$params->get('og_product_currency'),
			$params->get('og_product_availability')
		);
	}
}
