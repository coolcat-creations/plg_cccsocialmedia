<?php

use Joomla\CMS\Document\Document;
use Joomla\Event\Dispatcher;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

class SocialMediaTest extends TestCase
{
	/**
	 * Test data for the getTitle method
	 *
	 * @return \Generator
	 */
	public function casesTitle()
	{
		yield 'all set' => [
			'article'  => [
				'article_page_title' => 'Article Page Title',
			],
			'menu'     => [
				'page_title' => 'Menu Page Title',
			],
			'document' => [
				'title' => 'Document Title',
			],
			'expected' => 'Article Page Title',
		];
		yield 'empty article page title' => [
			'article'  => [
			],
			'menu'     => [
				'page_title' => 'Menu Page Title',
			],
			'document' => [
				'title' => 'Document Title',
			],
			'expected' => 'Menu Page Title',
		];
		yield 'empty article and menu page title' => [
			'article'  => [
			],
			'menu'     => [
			],
			'document' => [
				'title' => 'Document Title',
			],
			'expected' => 'Document Title',
		];
	}

	/**
	 * @testdox      Title fallback is retrieved with defined priority
	 * @dataProvider casesTitle
	 *
	 * @param   array   $articleProperties
	 * @param   array   $menuProperties
	 * @param   array   $documentProperties
	 * @param   string  $expected
	 *
	 * @return void
	 */
	public function testGetTitle(
		array $articleProperties,
		array $menuProperties,
		array $documentProperties,
		string $expected
	): void {
		$articleParams = $this->createRegistry($articleProperties);
		$menuParams    = $this->createRegistry($menuProperties);
		$document      = $this->createDocument($documentProperties);
		$plugin        = $this->createPlugin();

		$this->assertEquals(
			$expected,
			private_access($plugin)
				->getTitle($articleParams, $menuParams, $document)
		);
	}

	/**
	 * Test data for the getDescription method
	 *
	 * @return \Generator
	 */
	public function casesDescription(): Generator
	{
		yield 'all set for non-category' => [
			'menu'     => [
				'menu-meta_description' => 'Menu Meta Description',
				'page_description'      => 'Page Description',
			],
			'document' => [
				'description' => 'Document Description'
			],
			'view'     => 'article',
			'expected' => 'Document Description',
		];
		yield 'all set for category' => [
			'menu'     => [
				'menu-meta_description' => 'Menu Meta Description',
				'page_description'      => 'Page Description',
			],
			'document' => [
				'description' => 'Document Description'
			],
			'view'     => 'category',
			'expected' => 'Menu Meta Description',
		];
		yield 'empty menu meta description' => [
			'menu'     => [
				'page_description' => 'Page Description',
			],
			'document' => [
				'description' => 'Document Description'
			],
			'view'     => 'category',
			'expected' => 'Page Description',
		];
		yield 'empty menu meta and page description' => [
			'menu'     => [
			],
			'document' => [
				'description' => 'Document Description'
			],
			'view'     => 'category',
			'expected' => 'Document Description',
		];
	}

	/**
	 * @testdox      Description fallback is retrieved with defined priority
	 * @dataProvider casesDescription
	 *
	 * @param   array   $menuProperties
	 * @param   array   $documentProperties
	 * @param   string  $view
	 * @param   string  $expected
	 *
	 * @return void
	 */
	public function testGetDescription(
		array $menuProperties,
		array $documentProperties,
		string $view,
		string $expected
	): void {
		$menuParams = $this->createRegistry($menuProperties);
		$document   = $this->createDocument($documentProperties);
		$plugin     = $this->createPlugin();

		$this->assertEquals(
			$expected,
			private_access($plugin)
				->getDescription($menuParams, $document, $view)
		);
	}

	/**
	 * Test cases for bot detection
	 *
	 * @return \Generator
	 */
	public function casesBot(): Generator
	{
		yield 'googlebot' => [
			'useragent' => 'something containing googlebot as a signal',
			'expected'  => plgSystemCccsocialmedia::GOOGLE,
		];
		yield 'facebot' => [
			'useragent' => 'something containing facebot as a signal',
			'expected'  => plgSystemCccsocialmedia::FACEBOOK,
		];
		yield 'facebook' => [
			'useragent' => 'something containing facebook as a signal',
			'expected'  => plgSystemCccsocialmedia::FACEBOOK,
		];
		yield 'linkedinbot' => [
			'useragent' => 'something containing LinkedInBot as a signal',
			'expected'  => plgSystemCccsocialmedia::FACEBOOK,
		];
		yield 'pinterest' => [
			'useragent' => 'something containing pinterest as a signal',
			'expected'  => plgSystemCccsocialmedia::PINTEREST,
		];
		yield 'anything else' => [
			'useragent' => 'something containing none of the known signals',
			'expected'  => plgSystemCccsocialmedia::NONE,
		];
	}

	/**
	 * @testdox      Useragents are mapped correctly to bot identifiers
	 * @dataProvider casesBot
	 *
	 * @param   string  $useragent
	 * @param   string  $expected
	 *
	 * @return void
	 */
	public function testGetBot(string $useragent, string $expected): void
	{
		$app = new Application();
		$app->setUserAgent($useragent);

		$plugin = $this->createPlugin(
			[],
			$app
		);

		$this->assertEquals(
			$expected,
			private_access($plugin)
				->getBot()
		);
	}

	/**
	 * Test data for the addImageFallback method
	 *
	 * @return \Generator
	 */
	public function casesImage(): Generator
	{
		yield 'category view' => [
			'plugin'     => [
				'og_image'     => 'OG Image Google',
				'og_image_fb'  => 'OG Image Facebook',
				'og_image_pi'  => 'OG Image Pinterest',
				'og_image_alt' => 'OG Image alt text',
			],
			'images'     => [
				'image_intro'        => 'Article Intro Image',
				'image_intro_alt'    => 'Article Intro Image alt text',
				'image_fulltext'     => 'Article Fulltext Image',
				'image_fulltext_alt' => 'Article Fulltext Image alt text',
			],
			'view'       => 'category',
			'user_agent' => plgSystemCccsocialmedia::GOOGLE,
			'expected'   => [
				'og_image'     => 'OG Image Google',
				'og_image_alt' => 'OG Image alt text',
				'tw_image'     => null,
				'tw_image_alt' => null,
			]
		];
		yield 'article view google' => [
			'plugin'     => [
				'og_image'     => 'OG Image Google',
				'og_image_fb'  => 'OG Image Facebook',
				'og_image_pi'  => 'OG Image Pinterest',
				'og_image_alt' => 'OG Image alt text',
			],
			'images'     => [
				'image_intro'        => 'Article Intro Image',
				'image_intro_alt'    => 'Article Intro Image alt text',
				'image_fulltext'     => 'Article Fulltext Image',
				'image_fulltext_alt' => 'Article Fulltext Image alt text',
			],
			'view'       => 'article',
			'user_agent' => plgSystemCccsocialmedia::GOOGLE,
			'expected'   => [
				'og_image'     => 'OG Image Google',
				'og_image_alt' => 'OG Image alt text',
				'tw_image'     => 'OG Image Google',
				'tw_image_alt' => 'OG Image alt text',
			]
		];
		yield 'article view facebook' => [
			'plugin'     => [
				'og_image'     => 'OG Image Google',
				'og_image_fb'  => 'OG Image Facebook',
				'og_image_pi'  => 'OG Image Pinterest',
				'og_image_alt' => 'OG Image alt text',
			],
			'images'     => [
				'image_intro'        => 'Article Intro Image',
				'image_intro_alt'    => 'Article Intro Image alt text',
				'image_fulltext'     => 'Article Fulltext Image',
				'image_fulltext_alt' => 'Article Fulltext Image alt text',
			],
			'view'       => 'article',
			'user_agent' => plgSystemCccsocialmedia::FACEBOOK,
			'expected'   => [
				'og_image'     => 'OG Image Facebook',
				'og_image_alt' => 'OG Image alt text',
				'tw_image'     => 'OG Image Facebook',
				'tw_image_alt' => 'OG Image alt text',
			]
		];
		yield 'article view pinterest' => [
			'plugin'     => [
				'og_image'     => 'OG Image Google',
				'og_image_fb'  => 'OG Image Facebook',
				'og_image_pi'  => 'OG Image Pinterest',
				'og_image_alt' => 'OG Image alt text',
			],
			'images'     => [
				'image_intro'        => 'Article Intro Image',
				'image_intro_alt'    => 'Article Intro Image alt text',
				'image_fulltext'     => 'Article Fulltext Image',
				'image_fulltext_alt' => 'Article Fulltext Image alt text',
			],
			'view'       => 'article',
			'user_agent' => plgSystemCccsocialmedia::PINTEREST,
			'expected'   => [
				'og_image'     => 'OG Image Pinterest',
				'og_image_alt' => 'OG Image alt text',
				'tw_image'     => 'OG Image Pinterest',
				'tw_image_alt' => 'OG Image alt text',
			]
		];
		yield 'article view facebook w/o fb image' => [
			'plugin'     => [
				'og_image'     => 'OG Image Google',
				'og_image_pi'  => 'OG Image Pinterest',
				'og_image_alt' => 'OG Image alt text',
			],
			'images'     => [
				'image_intro'        => 'Article Intro Image',
				'image_intro_alt'    => 'Article Intro Image alt text',
				'image_fulltext'     => 'Article Fulltext Image',
				'image_fulltext_alt' => 'Article Fulltext Image alt text',
			],
			'view'       => 'article',
			'user_agent' => plgSystemCccsocialmedia::FACEBOOK,
			'expected'   => [
				'og_image'     => 'Article Intro Image',
				'og_image_alt' => 'Article Intro Image alt text',
				'tw_image'     => 'Article Intro Image',
				'tw_image_alt' => 'Article Intro Image alt text',
			]
		];
		yield 'article view facebook w/o fb image w/o intro image' => [
			'plugin'     => [
				'og_image'     => 'OG Image Google',
				'og_image_pi'  => 'OG Image Pinterest',
				'og_image_alt' => 'OG Image alt text',
			],
			'images'     => [
				'image_intro'        => '',
				'image_intro_alt'    => '',
				'image_fulltext'     => 'Article Fulltext Image',
				'image_fulltext_alt' => 'Article Fulltext Image alt text',
			],
			'view'       => 'article',
			'user_agent' => plgSystemCccsocialmedia::FACEBOOK,
			'expected'   => [
				'og_image'     => 'Article Fulltext Image',
				'og_image_alt' => 'Article Fulltext Image alt text',
				'tw_image'     => 'Article Fulltext Image',
				'tw_image_alt' => 'Article Fulltext Image alt text',
			]
		];
		yield 'article view pinterest w/o pi image' => [
			'plugin'     => [
				'og_image'     => 'OG Image Google',
				'og_image_fb'  => 'OG Image Facebook',
				'og_image_alt' => 'OG Image alt text',
			],
			'images'     => [
				'image_intro'        => 'Article Intro Image',
				'image_intro_alt'    => 'Article Intro Image alt text',
				'image_fulltext'     => 'Article Fulltext Image',
				'image_fulltext_alt' => 'Article Fulltext Image alt text',
			],
			'view'       => 'article',
			'user_agent' => plgSystemCccsocialmedia::PINTEREST,
			'expected'   => [
				'og_image'     => 'OG Image Facebook',
				'og_image_alt' => 'OG Image alt text',
				'tw_image'     => 'OG Image Facebook',
				'tw_image_alt' => 'OG Image alt text',
			]
		];
	}

	/**
	 * @testdox      Images are retrieved with defined priority depending on useragent
	 * @dataProvider casesImage
	 *
	 * @param   array   $pluginProperties
	 * @param   array   $articleImageProperties
	 * @param   string  $view
	 * @param   string  $userAgent
	 * @param   array   $expected
	 *
	 * @return void
	 */
	public function testAddImageFallback(
		array $pluginProperties,
		array $articleImageProperties,
		string $view,
		string $userAgent,
		array $expected
	): void {
		$pluginParams  = $this->createRegistry($pluginProperties);
		$articleImages = $this->createRegistry($articleImageProperties);
		$plugin        = $this->createPlugin();

		private_access($plugin)->addImageFallback($pluginParams, $articleImages, $view, $userAgent);

		$this->assertEquals(
			$expected['og_image'],
			$pluginParams->get('og_image'),
			'og_image is not set correctly'
		);

		$this->assertEquals(
			$expected['og_image_alt'],
			$pluginParams->get('og_image_alt'),
			'og_image_alt is not set correctly'
		);

		$this->assertEquals(
			$expected['tw_image'],
			$pluginParams->get('tw_image'),
			'tw_image is not set correctly'
		);

		$this->assertEquals(
			$expected['tw_image_alt'],
			$pluginParams->get('tw_image_alt'),
			'tw_image_alt is not set correctly'
		);
	}

	/**
	 * Test cases for the setOpenGraphImage method
	 *
	 * @return \Generator
	 */
	public function casesOGImage(): Generator
	{
		yield 'nothing' => [
			'document' => [],
			'image'    => null,
			'alt'      => null,
			'base_url' => null,
			'expected' => [],
		];
		yield 'image with non-existing image' => [
			'document' => [],
			'image'    => 'image?query=string',
			'alt'      => null,
			'base_url' => null,
			'expected' => [],
		];
		yield 'image with leftover query string' => [
			'document' => [],
			'image'    => 'tests/fixtures/powered_by.png?query=string',
			'alt'      => null,
			'base_url' => null,
			'expected' => [
				'property' => [
					'og:image'            => 'tests/fixtures/powered_by.png',
					'og:image:secure_url' => 'tests/fixtures/powered_by.png',
					'og:image:alt'        => null,
					'og:image:type' => 'image/png',
				],
				'name'     => [
					'og:image:width'  => '294',
					'og:image:height' => '44',
					'twitter:image' => 'tests/fixtures/powered_by.png',
				],
			],
		];
		yield 'no image in document' => [
			'document' => [],
			'image'    => 'tests/fixtures/powered_by.png',
			'alt'      => 'Powered by Joomla!',
			'base_url' => null,
			'expected' => [
				'property' => [
					'og:image'            => 'tests/fixtures/powered_by.png',
					'og:image:secure_url' => 'tests/fixtures/powered_by.png',
					'og:image:alt'        => 'Powered by Joomla!',
					'og:image:type'       => 'image/png',
				],
				'name'     => [
					'og:image:width'    => '294',
					'og:image:height'   => '44',
					'twitter:image'     => 'tests/fixtures/powered_by.png',
					'twitter:image:alt' => 'Powered by Joomla!',
				],
			],
		];
		yield 'another image in document' => [
			'document' => [
				'_metaTags' => [
					'property' => [
						'og:image' => 'preset-image.jpg',
					],
				],
			],
			'image'    => 'tests/fixtures/powered_by.png',
			'alt'      => 'Powered by Joomla!',
			'base_url' => null,
			'expected' => [
				'property' => [
					'og:image'            => 'tests/fixtures/powered_by.png',
					'og:image:secure_url' => 'tests/fixtures/powered_by.png',
					'og:image:alt'        => 'Powered by Joomla!',
					'og:image:type'       => 'image/png',
				],
				'name'     => [
					'og:image:width'    => '294',
					'og:image:height'   => '44',
					'twitter:image'     => 'tests/fixtures/powered_by.png',
					'twitter:image:alt' => 'Powered by Joomla!',
				],
			],
		];
		yield 'with base url' => [
			'document' => [],
			'image'    => 'tests/fixtures/powered_by.png',
			'alt'      => 'Powered by Joomla!',
			'base_url' => 'https://example.com/',
			'expected' => [
				'property' => [
					'og:image'            => 'https://example.com/tests/fixtures/powered_by.png',
					'og:image:secure_url' => 'https://example.com/tests/fixtures/powered_by.png',
					'og:image:alt'        => 'Powered by Joomla!',
					'og:image:type'       => 'image/png',
				],
				'name'     => [
					'og:image:width'    => '294',
					'og:image:height'   => '44',
					'twitter:image'     => 'https://example.com/tests/fixtures/powered_by.png',
					'twitter:image:alt' => 'Powered by Joomla!',
				],
			],
		];
		yield 'with base url without slash' => [
			'document' => [],
			'image'    => 'tests/fixtures/powered_by.png',
			'alt'      => 'Powered by Joomla!',
			'base_url' => 'https://example.com',
			'expected' => [
				'property' => [
					'og:image'            => 'https://example.com/tests/fixtures/powered_by.png',
					'og:image:secure_url' => 'https://example.com/tests/fixtures/powered_by.png',
					'og:image:alt'        => 'Powered by Joomla!',
					'og:image:type'       => 'image/png',
				],
				'name'     => [
					'og:image:width'    => '294',
					'og:image:height'   => '44',
					'twitter:image'     => 'https://example.com/tests/fixtures/powered_by.png',
					'twitter:image:alt' => 'Powered by Joomla!',
				],
			],
		];
	}

	/**
	 * @testdox      The required meta tags are set in the document
	 * @dataProvider casesOGImage
	 *
	 * @param   array        $documentProperties
	 * @param   string|null  $image
	 * @param   string|null  $alt
	 * @param   string|null  $baseUrl
	 * @param   array        $expected
	 *
	 * @return void
	 */
	public function testSetOpenGraphImage(
		array $documentProperties,
		?string $image,
		?string $alt,
		?string $baseUrl,
		array $expected
	): void {
		$document = $this->createDocument($documentProperties);
		$plugin   = $this->createPlugin();

		private_access($plugin)->setOpenGraphImage(
			$document,
			$image,
			$alt,
			$baseUrl
		);

		$this->assertEquals(
			$expected,
			$document->_metaTags
		);
	}

	/**
	 * Create a test document
	 *
	 * @param   array  $properties  Optional properties of the document
	 *
	 * @return \Joomla\CMS\Document\Document
	 */
	private function createDocument(array $properties = []): Document
	{
		$document = new Document();

		$document->title       = 'Document Title';
		$document->description = 'Document Description';

		foreach ($properties as $key => $value)
		{
			$document->$key = $value;
		}

		return $document;
	}

	/**
	 * Create a test registry
	 *
	 * @param   array  $properties  Optional properties of the registry
	 *
	 * @return \Joomla\Registry\Registry
	 */
	private function createRegistry(array $properties = []): Registry
	{
		return new Registry($properties);
	}

	/**
	 * Create a test plugin
	 *
	 * @param   array  $pluginParams
	 *
	 * @return \plgSystemCccsocialmedia
	 */
	private function createPlugin(array $pluginParams = [], object $app = null): plgSystemCccsocialmedia
	{
		$dispatcher = $this->createMock(Dispatcher::class);

		$plugin = new plgSystemCccsocialmedia(
			$dispatcher,
			[
				'params' => $pluginParams
			]
		);

		private_access($plugin)->app = $app;

		return $plugin;
	}
}
