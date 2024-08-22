<?php

namespace PL\Tests\Robo\Task\Testor {

    use Robier\MockGlobalFunction\MockFunction;

    class MockExec
    {
        protected MockFunction $spy;
        protected array $callLog = array();
        protected array $returnMap = array();

        public function __construct()
        {
            $this->spy = new MockFunction(str_replace('Tests\\', '', __NAMESPACE__), 'exec', function (string $command, &$output = null, &$result_code = null) {
                $this->callLog[] = $command;
                foreach ($this->returnMap as $item => $value) {
                    if (str_starts_with($command, $item)) {
                        $output = $value['output'];
                        $result_code = $value['code'];
                        return $value['return'];
                    }
                }
//                $this->spy->call(array($command, &$output, &$result_code));
            });
            $this->spy->enable();
        }

        public function __destruct()
        {
            $this->spy->disable();
        }

        public function on(string $prefix, string $output, int $code): void
        {
            // Map the return value by prefix, so far it's enough for our tests.
            // Output as array of string, as `exec` returns.
            $output = explode("\n", $output);
            // Return value of `exec`.
            $return = end($output);
            $this->returnMap[$prefix] = array(
                'output' => $output,
                'code' => $code,
                'return' => $return
            );
        }

        public function getCallList(): array
        {
            return $this->callLog;
        }
    }
}
