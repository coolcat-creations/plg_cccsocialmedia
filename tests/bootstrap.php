<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

const _JEXEC = 1;
const JPATH_ADMINISTRATOR = 1;
const JPATH_CONFIGURATION = 1;
const JPATH_PLATFORM = 1;

if (!function_exists('private_access')) {
	/**
	 * Access an objects internals
	 *
	 * When unit testing private methods:
	 *
	 * // Instead of using a helper method:
	 * $this->invokeMethod($user, 'encryptPassword', ['abcde']);
	 *
	 * // Use a wrapper class:
	 * private_access($user)->encryprPassword('abcde');
	 */
	function private_access(object $object)
	{
		return new PrivateAccess($object);
	}
}
