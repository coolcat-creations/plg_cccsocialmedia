<?php

namespace Joomla\CMS\Document;

use Joomla\Application\AbstractWebApplication;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory as CmsFactory;
use Joomla\CMS\WebAsset\WebAssetManager;
use Symfony\Component\WebLink\HttpHeaderSerializer;

/**
 * Document class, provides an easy interface to parse and display a document
 */
class Document
{
	/**
	 * Document title
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	public $title = '';

	/**
	 * Document description
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	public $description = '';

	/**
	 * Array of meta tags
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	public $_metaTags = array();

	/**
	 * Sets or alters a meta tag.
	 *
	 * @param   string  $name       Name of the meta HTML tag
	 * @param   mixed   $content    Value of the meta HTML tag as array or string
	 * @param   string  $attribute  Attribute to use in the meta HTML tag
	 *
	 * @return  Document instance of $this to allow chaining
	 */
	public function setMetaData($name, $content, $attribute = 'name')
	{
		// Pop the element off the end of array if target function expects a string or this http_equiv parameter.
		if (\is_array($content) && (\in_array($name, array('generator', 'description')) || !\is_string($attribute)))
		{
			$content = array_pop($content);
		}

		// B/C old http_equiv parameter.
		if (!\is_string($attribute))
		{
			$attribute = $attribute == true ? 'http-equiv' : 'name';
		}

		$this->_metaTags[$attribute][$name] = $content;

		return $this;
	}

	/**
	 * Gets a meta tag.
	 *
	 * @param   string  $name       Name of the meta HTML tag
	 * @param   string  $attribute  Attribute to use in the meta HTML tag
	 *
	 * @return  string
	 */
	public function getMetaData($name, $attribute = 'name')
	{
		// B/C old http_equiv parameter.
		if (!\is_string($attribute))
		{
			$attribute = $attribute == true ? 'http-equiv' : 'name';
		}

		return isset($this->_metaTags[$attribute]) && isset($this->_metaTags[$attribute][$name]) ? $this->_metaTags[$attribute][$name] : '';
	}

	/**
	 * Sets the description of the document
	 *
	 * @param   string  $description  The description to set
	 *
	 * @return  Document instance of $this to allow chaining
	 */
	public function setDescription($description)
	{
		$this->description = $description;

		return $this;
	}
}
