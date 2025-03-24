<?php

namespace PL\Robo\Task\Testor;

class TugboatPreviewSet extends TugboatTask {
  protected string|null $preview;
  protected string $framework;

  public function __construct(string|null $preview = null) {
    parent::__construct();
    $this->preview = $preview;

    // Check if we have playwright or cypress config.
    $framework = 'playwright';
    if (!file_exists('playwright.config.js') && file_exists('cypress.config.js')) {
      $framework = 'cypress';
    }
    $this->framework = $framework;
  }

  /**
   * Configure preview to set.
   *
   * @param string|array $preview Preview ID or preview JSON.
   * @return void
   */
  public function preview(string|array $preview): void {
    if (is_array($preview)) {
      $this->preview = $preview['preview'];
    }
    else {
      $this->preview = $preview;
    }
  }

  public function run(): \Robo\Result {
    if (!$this->initTugboat()) {
      return new \Robo\Result($this, 1, $this->message);
    }

    $result = $this->exec("$this->tugboat ls services preview=$this->preview --json", $output);
    if ($result->getExitCode() !== 0) {
      return $result;
    }

    $services = json_decode($output, true);
    if (!(bool) $services) {
      $this->message = "Invalid tugboat JSON:\n\n{$output}";
      return $this->fail();
    }
    $service = array_combine(array_map(fn($service) => $service['name'], $services), $services)['php'];
    if (!$service || !$service['urls'] || !$service['urls'][0] || !$service['id']) {
      $serviceJson = json_encode($service);
      $this->message = "Something is missing on the tugboat service:\n\n{$serviceJson}";
      return $this->fail();
    }
    $url = $service['urls'][0];
    $service = $service['id'];  // service as string replaces service as an object...

    // URL must end with '/' in ATK.
    if (!str_ends_with($url, '/')) {
      $url = $url . '/';
    }

    // Parse [playwright|cypress].config.js to change baseURL.
    // While we can use `peast` or some other sophisticated
    // libs here. Let keep it simple and use regexp for now.
    $config = file_get_contents("{$this->framework}.config.js");
    if (!$config) {
      $this->message = "{$this->framework}.config.js is missing";
      return $this->fail();
    }
    else {
      if (!($config = $this->changeConfig($config, ['baseURL' => $url]))) {
        return $this->fail();
      }

      file_put_contents("{$this->framework}.config.js", $config);
    }

    // Parse and change [playwright|cypress].atk.config.js
    $config = file_get_contents("{$this->framework}.atk.config.js");
    if (!$config) {
      $this->printTaskError("{$this->framework}.atk.config.js is missing");
    }
    else {
      if (!($config = $this->changeAtkConfig($config, ['service' => $service, 'isTarget' => 'true']))) {
        return $this->fail();
      }

      file_put_contents("$this->framework.atk.config.js", $config);
    }

    // Show success message.
    $this->message = "Tugboat preview [$this->preview]($url) is set in the tests config.";
    return $this->pass();
  }

  /**
   * Change [playwright|cypress].config.js.
   *
   * @param string $config Initial config as a string.
   * @param array $repl values to replace. Should contain `baseURL`
   * @return string
   */
  public function changeConfig(string $config, array $repl): string {
    // Moved to a method to allow test in isolation.
    // Both cypress and playwright config must have single-quoted strings.
    if ($this->framework === 'playwright') {
      $config = preg_replace(
        "~(//[[:space:]]*)?baseURL: '.*'~",
        "baseURL: '{$repl['baseURL']}'",
        $config,
        1, $count);
    }
    elseif ($this->framework === 'cypress') {
      $config = preg_replace(
        "~(//[[:space:]]*)?baseUrl: '.*'~",
        "baseUrl: '{$repl['baseURL']}'",
        $config,
        1, $count
      );
    } else {
      $this->message = "Framework {$this->framework} is not operable by Testor";
      return false;
    }
    if (!$count) {
      $this->message = "`{$this->framework}.config.js` hasn't been changed: baseURL not set";
      return false;
    }
    return $config;
  }

  /**
   * Change playwright.atk.config.js.
   *
   * @param string $config Initial config as a string.
   * @param array $repl Replacement array, should contain `service`, `isTarget`.
   * @return string|false New config as a string, or false if change wasn't successful.
   */
  public function changeAtkConfig(string $config, array $repl): string|false {
    // Since PHP is missing sed (as quick search reveal, maybe I'm wrong),
    // let read a file line by line and change a line if
    // it is in "tugboat" block.

    // By now, config has single-quoted strings.
    // But keep code for double-quoted legacy strings as well.

    $lines = explode("\n", $config);
    $config = "";
    $f = false;
    $isTargetIsSet = 0;
    $serviceIsSet = 0;
    foreach ($lines as $line) {
      if (preg_match('~tugboat: {~', $line)) $f = 1;
      if (preg_match('~}~', $line)) $f = 0;
      if ($f) {
        $line = preg_replace('~isTarget: (false|true)~', "isTarget: {$repl['isTarget']}", $line, 1, $count);
        $isTargetIsSet |= $count;
        $line = preg_replace('~service: "[^"]*"~', "service: \"{$repl['service']}\"", $line, 1, $count);
        $serviceIsSet |= $count;
        $line = preg_replace('~service: \'[^\']*\'~', "service: '{$repl['service']}'", $line, 1, $count);
        $serviceIsSet |= $count;
      }
      else {
        $line = preg_replace('~isTarget: true~', 'isTarget: false', $line);
      }
      $config .= $line . "\n";
    }
    if (!$isTargetIsSet) {
      $this->message = "`{$this->framework}.atk.config.js` hasn't been changed: tugboat.isTarget not set";
      return false;
    }
    if (!$serviceIsSet) {
      $this->message = "`{$this->framework}.atk.config.js` hasn't been changed: tugboat.service not set";
      return false;
    }
    return $config;
  }

}