<?php
/**
 * Pimf
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT
 */

namespace Pimf;

/**
 * @package Pimf
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
class Database extends \PDO
{
    /**
     * The current transaction level.
     *
     * @var int
     */
    protected $transLevel = 0;

    /**
     * Check database drivers that support savepoints.
     *
     * @return bool
     */
    public function nestable()
    {
        return in_array(
            $this->getAttribute(\PDO::ATTR_DRIVER_NAME),
            ["pgsql", "mysql"]
        );
    }

    /**
     * @return bool|void
     */
    public function beginTransaction()
    {
        if ($this->transLevel === 0 || $this->nestable() === false) {
            parent::beginTransaction();
        } else {
            $this->exec("SAVEPOINT LEVEL{$this->transLevel}");
        }

        $this->transLevel++;
    }

    /**
     * @return bool|void
     */
    public function commit()
    {
        $this->transLevel--;

        if ($this->transLevel === 0 || $this->nestable() === false) {
            parent::commit();
            return;
        }
        $this->exec("RELEASE SAVEPOINT LEVEL{$this->transLevel}");
    }

    /**
     * @return bool|void
     * @throws \LogicException
     */
    public function rollBack()
    {
        if ($this->transLevel === 0) {
            throw new \LogicException('trying to rollback without a transaction-start', 25000);
        }

        $this->transLevel--;

        if ($this->transLevel === 0 || $this->nestable() === false) {
            parent::rollBack();
            return;
        }
        $this->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->transLevel}");
    }
}
