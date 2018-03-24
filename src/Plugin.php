<?php

/**
 * @package Dbmover
 * @subpackage Procedures
 */

namespace Dbmover\Procedures;

use Dbmover\Core;
use PDO;

class Plugin extends Core\Plugin
{
    const REGEX = '@^CREATE (PROCEDURE|FUNCTION).*?^END;$@ms';
    const DROP_ROUTINE_SUFFIX = '()';

    /** @var string */
    public $description = 'Dropping existing procedures...';

    /**
     * @param string $sql
     * @return string
     */
    public function __invoke(string $sql) : string
    {
        $this->dropExistingProcedures();
        if (preg_match_all(static::REGEX, $sql, $procedures, PREG_SET_ORDER)) {
            foreach ($procedures as $procedure) {
                $this->defer($procedure[0]);
                $sql = str_replace($procedure[0], '', $sql);
            }
        }
        return $sql;
    }

    /**
     * @return void
     */
    protected function dropExistingProcedures() : void
    {
        $stmt = $this->loader->getPdo()->prepare(sprintf(
            "SELECT
                ROUTINE_TYPE routinetype,
                ROUTINE_NAME routinename
            FROM INFORMATION_SCHEMA.ROUTINES WHERE
                (ROUTINE_CATALOG = '%1\$s' OR ROUTINE_SCHEMA = '%1\$s')",
            $this->loader->getDatabase()
        ));
        $stmt->execute();
        while (false !== ($routine = $stmt->fetch(PDO::FETCH_ASSOC))) {
            if (!$this->loader->shouldBeIgnored($routine)) {
                $this->addOperation(sprintf(
                    "DROP %s %s%s",
                    $routine['routinetype'],
                    $routine['routinename'],
                    static::DROP_ROUTINE_SUFFIX
                ));
            }
        }
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->description = 'Recreating procedures...';
        parent::__destruct();
    }
}

