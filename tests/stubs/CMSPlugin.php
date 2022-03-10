<?php

namespace Joomla\CMS\Plugin;

use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;

/**
 * Plugin Class
 */
abstract class CMSPlugin
{
	/**
	 * A Registry object holding the parameters for the plugin
	 *
	 * @var    Registry
	 */
	public $params;

	/**
	 * The name of the plugin
	 *
	 * @var    string
	 */
	protected $_name;

	/**
	 * The plugin type
	 *
	 * @var    string
	 */
	protected $_type;

	/**
	 * Constructor
	 *
	 * @param   DispatcherInterface  &$subject  The object to observe
	 * @param   array                 $config   An optional associative array of configuration settings.
	 *                                          Recognized key values include 'name', 'group', 'params', 'language'
	 *                                          (this list is not meant to be comprehensive).
	 */
	public function __construct(&$subject, $config = array())
	{
		// Get the parameters.
		if (isset($config['params']))
		{
			if ($config['params'] instanceof Registry)
			{
				$this->params = $config['params'];
			}
			else
			{
				$this->params = new Registry($config['params']);
			}
		}

		// Get the plugin name.
		if (isset($config['name']))
		{
			$this->_name = $config['name'];
		}

		// Get the plugin type.
		if (isset($config['type']))
		{
			$this->_type = $config['type'];
		}
	}
}
