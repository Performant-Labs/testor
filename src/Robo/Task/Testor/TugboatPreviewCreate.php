<?php

namespace PL\Robo\Task\Testor;

class TugboatPreviewCreate extends TugboatTask
{

    public function run()
    {
        if (!$this->initTugboat()) {
            return $this->fail();
        }

        $runDate = date("Y-m-d H:i:s");
        $githubBranch = getenv('GITHUB_BRANCH');
        if (empty($githubBranch)) {
            $result = $this->exec('git branch --no-color --show-current', $githubBranch);
            if ($result->getExitCode() != 0) {
                return $result;
            }
            $githubBranch = trim($githubBranch);
        }
        $label = "Branch:$githubBranch $runDate";

        $this->printTaskInfo("Creating preview ($label).");
        $result = $this->exec("$this->tugboat create preview \"$githubBranch\" base=false repo=$this->repo label=\"$label\" output=json", $output);
        if ($result->getExitCode() != 0) {
            return $result;
        }
        $result['preview'] = json_decode($output, true);
        return $result;
    }
}