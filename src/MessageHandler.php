<?php

namespace Nyumbani\Messaging;

use InvalidArgumentException;
use ReflectionMethod;

abstract class MessageHandler
{
    /**
     * The message being built
     */
    protected Message $message;

    /**
     * Build the message body with the given method
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    final public function build(Message $message, string $method = 'handle'): Message
    {
        //  Refuse a method the handler cannot safely run
        $this->assertBuildable($method);

        //  Attach the message
        $this->message = $message;

        //  Build the body
        $this->$method();

        //  Return the message
        return $message;
    }

    /**
     * Define the default message
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    abstract protected function handle(): void;

    /**
     * Throw unless the method is one the handler declares itself, runnable without arguments
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    private function assertBuildable(string $method): void
    {
        //  Only inspect a method that exists
        $reflection = method_exists($this, $method) ? new ReflectionMethod($this, $method) : null;

        //  A buildable method belongs to the handler, not to this base class
        $isBuildable = $reflection
            && $reflection->class !== self::class
            && ! $reflection->isPrivate()
            && ! $reflection->isStatic()
            && ! str_starts_with($method, '__')
            && $reflection->getNumberOfRequiredParameters() === 0;

        //  Refuse anything else with a clear message
        if (! $isBuildable) {
            throw new InvalidArgumentException(static::class."::{$method}() cannot build a message: it must be a protected or public method of the handler that takes no required arguments.");
        }
    }
}
