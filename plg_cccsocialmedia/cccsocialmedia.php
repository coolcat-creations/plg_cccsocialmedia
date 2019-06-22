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
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;

defined('_JEXEC') or die;

/**
 * CCC Socialmedia plugin.
 *
 * @package  CCC socialmedia
 * @since    1.0
 */
class plgSystemCccsocialmedia extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  1.0
	 */
	protected $app;

	/**
	 * Database object
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

	function onContentPrepareForm($form, $data)
	{

		$app = JFactory::getApplication();
		$option = $app->input->get('option');


		if (!($form instanceof JForm)) {
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}

		switch ($option) {

			case 'com_menus':
				{
					if ($app->isAdmin()) {
						JForm::addFormPath(__DIR__ . '/forms');
						$form->loadFile('cccsocialmedia_menu', false);
					}

					return true;
				}

			case 'com_content':
				{
					if ($app->isAdmin()) {
						JForm::addFormPath(__DIR__ . '/forms');
						$form->loadFile('cccsocialmedia_article', false);
					}

					if ($app->isSite()) {
						JForm::addFormPath(__DIR__ . '/forms');
						$form->loadFile('cccsocialmedia_article', false);
					}

					return true;
				}

		}
		return true;
	}

	public function onContentBeforeSave($context, $article, $isNew)
	{
		$app = JFactory::getApplication();
		$option = $app->input->get('option');

		switch ($option) {

			case 'com_menus':
				{
					return true;
				}

			case 'com_content':
				{
					// getting the data from the submitted content
					$attribs = json_decode($article->attribs);

					$article->attribs = json_encode($attribs);

					return true;
				}

		}

	}

	public function onBeforeCompileHead()
	{
		$document = Factory::getDocument();
		$config = Factory::getConfig();
		$jinput = Factory::getApplication()->input;
		$option = $jinput->get('option', '', 'CMD');
		$view = $jinput->get('view', '', 'CMD');
		$context = $option . '.' . $view;
		$id = (int)$jinput->get('id', '', 'CMD');

		$article = $this->getObjectContent($id);

		if ($context == 'com_finder.indexer') {
			return true;
		}

		if ($jinput->get('format', '', 'CMD') == 'feed') {
			return true;
		}

		if ($this->app->isSite()) {

			$app = Factory::getApplication();
			$menu = $app->getMenu();
			$active = $menu->getActive();
			$itemId = $active->id;
			$menu->params = $menu->getParams($itemId);

			$useragent = false;

			if ($_SERVER['HTTP_USER_AGENT']) {
				$useragent = $_SERVER['HTTP_USER_AGENT'];
			}

			if ($useragent) {
				$this->googlebot = false;
				$this->facebookbot = false;
				$this->pinterestbot = false;

				if (stristr($useragent, "googlebot")) {
					$this->googlebot = true;
				}

				if (stristr($useragent, "facebot") || stristr($useragent, "facebook")) {
					$this->facebookbot = true;
					if (($this->app->get('gzip') == 1)) {
						$this->app->set('gzip', 0);
					}
				}

				if (stristr($useragent, "LinkedInBot") || stristr($useragent, "facebook")) {
					if (($this->app->get('gzip') == 1)) {
						$this->app->set('gzip', 0);
					}
				}

				if (stristr($useragent, "pinterest")) {
					$this->pinterestbot = true;
				}
			}

			if (empty($this->app)) {
				$this->app = Factory::getApplication();
			}

			if (isset($menu->params) || isset ($article->attribs)) {

				//defined in the menu item
				$menuParams = json_decode($menu->params);

				//defined in the article
				$articleAttribs = json_decode($article->attribs, true);
				$articleImages = json_decode($article->images);


				// all possible plugin settings in an array

				$mySocialMediaSettings = array(
					'og_title', 'og_type', 'og_description', 'og_image', 'og_image_fb', 'og_image_pi', 'og_image_alt', 'og_article_published_time', 'og_article_author', 'og_product_availability', 'og_product_price_currency', 'og_product_price_amount',
					'tw_title', 'tw_description', 'tw_image', 'tw_image_alt', 'tw_site', 'tw_type', 'tw_creator');


				foreach ($mySocialMediaSettings as $mySocialMediaSetting) {

					// if global plugin params exist - priority C

					if (!empty($this->params->get($mySocialMediaSetting))) {
						$this->{$mySocialMediaSetting} = $this->params->get($mySocialMediaSetting);
					} // if article params exist - priority B

					elseif (!empty($articleAttribs[$mySocialMediaSetting])) {
						$this->{$mySocialMediaSetting} = $articleAttribs[$mySocialMediaSetting];
					} // if menu params exist - priority A

					elseif (!empty($menuParams->$mySocialMediaSetting)) {
						$this->{$mySocialMediaSetting} = $menuParams->$mySocialMediaSetting;

					} else {
						$this->{$mySocialMediaSetting} = false;

					}

					// Testing the parameters
					// echo $mySocialMediaSetting . '  ' . $this->{$mySocialMediaSetting} . '<br>';

				}


				// Creating Fallbacks

				// if articleIntro Image exist take this one


				// if any of the og Images are empty create a Fallback to the articleIntro Image

				if ($view == 'article') {

					if (!$this->og_image || $this->og_image == "") {

						if ($articleImages->image_intro) {
							$this->og_image = $articleImages->image_intro;
						}
						if ($articleImages->image_fulltext) {
							$this->og_image = $articleImages->image_fulltext;
						}
					}

					if (!$this->og_image_fb || $this->og_image_fb == "") {
						if ($articleImages->image_intro) {
							$this->og_image_fb = $articleImages->image_intro;
						}
						if ($articleImages->image_fulltext) {
							$this->og_image_fb = $articleImages->image_fulltext;
						}
					}

					if (!$this->og_image_pi || $this->og_image_pi == "") {
						if ($articleImages->image_intro) {
							$this->og_image_pi = $articleImages->image_intro;
						}
						if ($articleImages->image_fulltext) {
							$this->og_image_pi = $articleImages->image_fulltext;
						}
					}

					if (!$this->tw_image || $this->tw_image == "") {
						if ($articleImages->image_intro) {
							$this->tw_image = $articleImages->image_intro;
						}
						if ($articleImages->image_fulltext) {
							$this->tw_image = $articleImages->image_fulltext;
						}
					}

					// if a og image alt does not exist but image alt exists and the intro image
					if (!$this->og_image_alt || $this->og_image_alt == "") {
						if ($articleImages->image_intro_alt && $articleImages->image_intro) {
							$this->og_image_alt = $articleImages->image_intro_alt;
							$this->tw_image_alt = $articleImages->image_intro_alt;
						}

						if ($articleImages->image_fulltext_alt && $articleImages->image_fulltext) {
							$this->og_image_alt = $articleImages->image_intro_alt;
							$this->tw_image_alt = $articleImages->image_intro_alt;
						}
					}
				}

				if (!$this->og_title || $this->og_title == "") {

					// Set the OG Title to Page Title if its there

					if ($menu->params['page_title']) {
						$this->og_title = $menu->params['page_title'];
					}

					// Set the OG Title to Article Title if its there (Overwrites Page Title)

					if (isset($articleAttribs['article_page_title'])) {
						$this->og_title = $articleAttribs['article_page_title'];
					}
				}

				if (!$this->tw_title || $this->tw_title == "") {
					if ($menu->params['page_title']) {
						$this->tw_title = $menu->params['page_title'];
					}
					if (isset($articleAttribs['article_page_title'])) {
						$this->tw_title = $articleAttribs['article_page_title'];
					}
				}


				// gets the page description if nothing else is set

				if (!$this->og_description || $this->og_description == "") {

					if ($view == 'category') {
						if ($document->description) {
							$this->og_description = $document->description;
						}

						if ($menu->params['page_description']) {
							$this->og_description = $menu->params['page_description'];
						}
						if ($menu->params['menu-meta_description']) {
							$this->og_description = $menu->params['menu-meta_description'];
						}

					} else {
						if ($document->description) {
							$this->og_description = $document->description;
						}
					}


				}

				if (!$this->tw_description || $this->tw_description == "") {

					if ($view == 'category') {

						if ($document->description) {
							$this->og_description = $document->description;
						}

						if ($menu->params['page_description']) {
							$this->tw_description = $menu->params['page_description'];
						}
						if ($menu->params['menu-meta_description']) {
							$this->tw_description = $menu->params['menu-meta_description'];
						}
					} else {
						if ($document->description) {
							$this->og_description = $document->description;
						}
					}

				}


				if (!$this->og_article_published_time || $this->og_article_published_time == "") {

					// get the creation date
					if ($article->created) {
						$this->og_article_published_time = $article->created;
					}

				}

				// gets the page title if nothing else is set

				if (!$this->og_title || $this->og_title == "") {

					if ($view == 'category') {
						if ($document->title) {
							$this->og_title = $document->title;
						}

						if ($menu->params['page_title']) {
							$this->og_title = $menu->params['page_title'];
						}

					}

					if ($view == 'article') {

						if ($menu->params['page_title']) {
							$this->og_title = $menu->params['page_title'];
						}

						if ($document->title) {
							$this->og_title = $document->title;
						}

					}

				}

				if (!$this->tw_title || $this->tw_title == "") {
					if ($menu->params['page_title']) {
						$this->tw_title = $menu->params['page_title'];
					}

					if ($document->title) {
						$this->tw_title = $document->title;
					}

				}

			}


			//Setup the metadata

			if ($this->tw_type && !$document->getMetaData('twitter:card')) {
				$document->setMetaData('twitter:card', $this->tw_type, 'name');
			}
			if ($this->tw_site && !$document->getMetaData('twitter:site') && $this->tw_site != '@username') {
				$document->setMetaData('twitter:site', $this->tw_site, 'name');
			}
			if ($this->tw_creator && !$document->getMetaData('twitter:creator')) {
				$document->setMetaData('twitter:creator', $this->tw_creator, 'name');
			}
			if ($this->tw_title && !$document->getMetaData('twitter:title')) {
				$document->setMetaData('twitter:title', $this->tw_title, 'name');
			}
			if ($this->tw_description && !$document->getMetaData('twitter:description')) {
				$document->setMetaData('twitter:description', $this->tw_description, 'name');
			}
			if ($this->tw_image && !$document->getMetaData('twitter:image')) {
				$document->setMetaData('twitter:image', Uri::base() . $this->tw_image, 'name');
				$document->setMetaData('twitter:image:alt', $this->tw_image_alt, 'name');
			}


			$document->setMetaData('og:site_name', $config->get('sitename'), 'property');

			$document->setMetaData('og:url', Uri::getInstance(), 'property');


			if ($this->og_type && !$document->getMetaData('og:type')) {
				$document->setMetaData('og:type', $this->og_type, 'property');
			}

			if ($this->og_title && !$document->getMetaData('og:title')) {
				$document->setMetaData('og:title', $this->og_title, 'property');
			}

			if ($this->og_description && !$document->getMetaData('og:description')) {
				$document->setMetaData('og:description', $this->og_description, 'property');
			}

			if ($this->og_article_published_time && !$document->getMetaData('article:published_time')) {
				$document->setMetaData('article:published_time', $this->og_article_published_time, 'property');
			}

			if ($this->og_article_author && !$document->getMetaData('article:author')) {
				$document->setMetaData('article:author', $this->og_article_author, 'property');
			}


			if ($this->og_type == 'product') {
				if ($this->og_product_price && !$document->getMetaData('product:price:amount')) {
					$document->setMetaData('product:price:amount', $this->og_product_price, 'property');
				}

				if ($this->og_product_currency && !$document->getMetaData('product:price:currency')) {
					$document->setMetaData('product:price:currency', $this->og_product_price, 'property');
				}

				if ($this->og_product_availability && !$document->getMetaData('og:availability')) {
					$document->setMetaData('og:availability', $this->og_product_price, 'property');
				}
			}


			if (($this->og_image || $this->og_image_fb || $this->og_image_pi) && !$document->getMetaData('og:image')) {

				if (!function_exists('addOpenGraphImage')) {
					function addOpenGraphImage($file)
					{
						$document = JFactory::getDocument();

						$document->setMetaData('og:image', Uri::base() . $file, 'property');

						$image_mime = image_type_to_mime_type(exif_imagetype($file));

						$imageinfo = getimagesize($file);
						$ix = $imageinfo[0];
						$iy = $imageinfo[1];

						$document->setMetaData('og:image:type', $image_mime, 'property');
						$document->setMetaData('og:image:height', $iy);
						$document->setMetaData('og:image:width', $ix);
						$document->setMetaData('og:image:secure_url', Uri::base() . $file, 'property');
					}
				}

				// deliver open graph image based on user agent: Facebook, Google or Pinterest - Fallback to Facebook because it's the most used format

				if ($this->facebookbot && $this->og_image_fb) {
					addOpenGraphImage($this->og_image_fb);
				} elseif ($this->googlebot && $this->og_image) {
					addOpenGraphImage($this->og_image);
				} elseif ($this->pinterestbot && $this->og_image_pi) {
					addOpenGraphImage($this->og_image_pi);
				} else {
					addOpenGraphImage($this->og_image_fb);
				}

				$document->setMetaData('og:image:alt', $this->og_image_alt, 'property');

			}

		}
	}

	private function getObjectContent($id, $table = 'content')
	{
		$db = JFactory::getDbo();

		$dataobject = JTable::getInstance($table);
		$dataobject->load($id);

		return $dataobject;
	}

	private function getObjectMenu($id, $table = 'menu')
	{
		$db = JFactory::getDbo();

		$dataobject = JTable::getInstance($table);
		$dataobject->load($id);

		return $dataobject;
	}

}
