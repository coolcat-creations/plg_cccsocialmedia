<?php

/**
 * Use Spatie(Invade instead!
 */
class PrivateAccess
{
	private $object;

	public function __construct(object $object) {
		$this->object = $object;
	}

	/**
	 * @throws ReflectionException
	 */
	public function __call(string $methodName, array $arguments)
	{
		$class  = new ReflectionClass($this->object);
		$method = $class->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs($this->object, $arguments);
	}

	/**
	 * @throws ReflectionException
	 */
	public function __get(string $name)
	{
		$class    = new ReflectionObject($this->object);
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		return $property->getValue($this->object);
	}

	/**
	 * @throws ReflectionException
	 */
	public function __set(string $name, $value): void
	{
		$reflection         = new ReflectionClass($this->object);
		$reflectionProperty = $reflection->getProperty($name);
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($this->object, $value);
	}
}
