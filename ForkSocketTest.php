<?php
/**
 * This is a test of a test.
 * A large portion of my integration tests involve socket setups.
 * The experiment here is to set up one end of the socket, fork, then test
 * against that socket.
 *
 * This will streamline automation of socket integration tests if I can pull
 * it off.
 *
 * User: eveil
 * Date: 7/24/14
 */

class ForkSocketTest extends PHPUnit_Framework_TestCase{

    /**
     * Simple as possible echo listener.
     *
     * Listens once then returns at the first newline.
     * Echos the line back to the sender.
     * The test environment catches any errors.
     *
     * @return string The newline terminated data from the socket.
     * @note If the data does not end in a newline, the socket will hang open
     * indefinitely.
     */
    function listener()
    {
        $protocol=getprotobyname("tcp");
        if($protocol===false) $protocol=SOL_TCP;
        $sock=socket_create(AF_INET,SOCK_STREAM,$protocol);
        socket_set_option($sock,SOL_SOCKET,SO_REUSEADDR,1);
        $ip="192.168.1.78";
        $port=50503;
        /* Must wait for socket to free up, otherwise,
        multiple tests will collide, and will only pass if run one at a time.*/
        while(!@socket_bind($sock,$ip,$port)){}
        socket_listen($sock,SOMAXCONN);
        $connection=socket_accept($sock);
        $max_chars=5000;
        $buf=socket_read($connection,$max_chars,PHP_NORMAL_READ);
        socket_write($connection,$buf,strlen($buf));
        socket_close($connection);
        socket_close($sock);
        return $buf;
    }

    /**
     * Simple as possible client.
     *
     * Inserts a message into the socket, obtains a response up to the
     * eof, then returns.
     * The test environment will catch any errors.
     *
     * @param $message string The data to send.
     * @return string The response from the socket.
     */
    function client($message)
    {
        $ip="192.168.1.78";
        $port=50503;
        /* Like the listener, you must wait for the socket to free up. */
        do{
            $socket=@fsockopen($ip,$port);
        }while($socket===false);
        fwrite($socket,$message);
        $buf="";
        while(!feof($socket)) $buf.=fgets($socket);
        fclose($socket);
        return $buf;
    }

    /**
     * Sets up a listener, then asserts that it responds correctly to a
     * tested client.
     */
    function test_clientTest()
    {
        $pid=pcntl_fork();
        if($pid==-1){ throw new Exception("Fork failed."); }
        else if ($pid) {
            /* The parent gets the mock end of the test. That way,
            it can wait until the child, bearing the assertion,
            is complete and then exit. */
            //parent
            $this->listener();
            pcntl_wait($status);
            /* We exit the parent after the wait so that we do not end up
            re-running 2^n tests, where n is the number of written tests.
            Therefore, the tests continue running down the child fork,
            each becoming parents to their own children. When the last one
            dies, the parents all end their waits. */
            exit(0);
        }
        else{
            //child
            $expected="test\n";
            $actual=$this->client($expected);
            $this->assertEquals($expected,$actual);
        }
    }

    /**
     * This time we test the listener with the assertion,
     * using the client as the test mock.
     */
    function test_listenerTest()
    {
        $expected="test\n";
        $pid=pcntl_fork();
        if($pid==-1){ throw new Exception("Fork failed."); }
        else if ($pid) {
            //parent
            $this->client($expected);
            pcntl_wait($status);
            exit(0);
        }
        else{
            //child
            $actual=$this->listener();
            $this->assertEquals($expected,$actual);
        }
    }

    /**
     * This test exists to make sure that the forks don't result in
     * exponential tests.
     */
    function test_listenerTest2()
    {
        $expected="test\n";
        $pid=pcntl_fork();
        if($pid==-1){ throw new Exception("Fork failed."); }
        else if ($pid) {
            //parent
            $this->client($expected);
            pcntl_wait($status);
            exit(0);
        }
        else{
            //child
            $actual=$this->listener();
            $this->assertEquals($expected,$actual);
        }
    }
}
