<?php
/**
 * Util
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */

namespace Pimf\Util;

/**
 * A class that generates RFC 4122 UUIDs
 *
 * <pre>
 * This specification defines a Uniform Resource Name namespace for
 * UUIDs (Universally Unique IDentifier), also known as GUIDs (Globally
 * Unique IDentifier).  A UUID is 128 bits long, and requires no central
 * registration process.
 * </pre>
 *
 * @package Util
 * @author  Gjero Krsteski <gjero@krsteski.de>
 * @see     http://www.ietf.org/rfc/rfc4122.txt
 */
final class Uuid
{
    /**
     * 32-bit integer that identifies this host.
     *
     * @var integer
     */
    private $node;

    /**
     * Process identifier.
     *
     * @var integer
     */
    private $pid;

    /**
     * IP address of this host.
     *
     * @var string
     */
    private $hostIp;

    /**
     * Hostname of this host.
     *
     * @var string
     */
    private $hostName;

    /**
     * @param string|null $hostIp
     * @param string|null $hostName
     */
    public function __construct($hostIp = null, $hostName = null)
    {
        $this->hostIp = $hostIp;
        $this->hostName = $hostName;
    }

    /**
     * Returns a 32-bit integer that identifies this host.
     *
     * The node identifier needs to be unique among nodes
     * in a cluster for a given application in order to
     * avoid collisions between generated identifiers.
     *
     * @return integer
     */
    private function getNodeId()
    {
        if (is_string($this->hostIp)) {
            return ip2long($this->hostIp);
        }

        $this->hostIp = '127.0.0.1';

        if (is_string($this->hostName)) {
            $this->hostIp = crc32($this->hostName);
        }

        if (true === function_exists('php_uname')) {
            $this->hostName = php_uname('n');
            $this->hostIp = gethostbyname($this->hostName);
        }

        if (true === function_exists('gethostname')) {
            $this->hostName = gethostname();
            $this->hostIp = gethostbyname($this->hostName);
        }

        return ip2long($this->hostIp);
    }

    /**
     * Returns a process identifier.
     *
     * In multi-process servers, this should be the system process ID.
     * In multi-threaded servers, this should be some unique ID to
     * prevent two threads from generating precisely the same UUID
     * at the same time.
     *
     * @return integer
     */
    private static function getLockId()
    {
        return getmypid();
    }

    /**
     * Generate an RFC 4122 UUID.
     *
     * This is pseudo-random UUID influenced by the system clock, IP
     * address and process ID.
     *
     * The intended use is to generate an identifier that can uniquely
     * identify user generated posts, comments etc. made to a website.
     * This generation process should be sufficient to avoid collisions
     * between nodes in a cluster, and between apache children on the
     * same host.
     *
     * @return string
     */
    public function generate()
    {
        if ($this->node === null) {
            $this->node = $this->getNodeId();
        }

        if ($this->pid === null) {
            $this->pid = self::getLockId();
        }

        list($timeMid, $timeLo) = explode(' ', microtime());

        $timeLow = (int)$timeLo;
        $timeMid = (int)substr($timeMid, 2);

        $timeAndVersion = mt_rand(0, 0xfff);

        // version 4 UUID
        $timeAndVersion |= 0x4000;

        $clockSeqLow = mt_rand(0, 0xff);

        // type is pseudo-random
        $clockSeqHigh = mt_rand(0, 0x3f);
        $clockSeqHigh |= 0x80;

        $nodeLow = $this->pid;
        $node = $this->node;

        return sprintf(
            '%08x-%04x-%04x-%02x%02x-%04x%08x', $timeLow, $timeMid & 0xffff, $timeAndVersion, $clockSeqHigh,
            $clockSeqLow, $nodeLow,
            $node
        );
    }
}
