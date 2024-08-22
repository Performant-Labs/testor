<?php

namespace PL\Tests\Robo\Task\Testor {

    use Robier\MockGlobalFunction\MockFunction;

    class MockIsExecutable
    {
        protected MockFunction $spy;
        protected array $resultMap;
        protected array $callLog = array();

        public function __construct()
        {
            $this->spy = new MockFunction(str_replace('\Tests', '', __NAMESPACE__), 'is_executable', function (string $program) {
                    $this->callLog[] = $program;
                    if (array_key_exists($program, $this->resultMap)) {
                        return $this->resultMap[$program];
                    }
// Here should call the initial function, but I don't know how to save a reference to it in PHP.
//                    return $this->spy->call(array($program));
                });
        }

        public function set(string $program, bool $isExecutable): void
        {
            $this->resultMap[$program] = $isExecutable;
        }
    }
}
