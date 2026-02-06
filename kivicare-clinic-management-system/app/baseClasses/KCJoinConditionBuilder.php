<?php
namespace App\baseClasses;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Class KCJoinConditionBuilder
 * 
 * This class is used to build join conditions for database queries.
 */
class KCJoinConditionBuilder
{
    private array $conditions = [];

    /**
     * Add an AND condition to the join
     *
     * @param string $firstColumn First column in the condition
     * @param string $operator Comparison operator
     * @param string $secondColumn Second column in the condition
     * @return self
     */
    public function on(string $firstColumn, string $operator, string $secondColumn): self
    {
        $this->conditions[] = [
            'firstColumn' => $firstColumn,
            'operator' => $operator,
            'secondColumn' => $secondColumn,
            'type' => 'AND'
        ];

        return $this;
    }

    /**
     * Add an OR condition to the join
     *
     * @param string $firstColumn First column in the condition
     * @param string $operator Comparison operator
     * @param string $secondColumn Second column in the condition
     * @return self
     */
    public function orOn(string $firstColumn, string $operator, string $secondColumn): self
    {
        $this->conditions[] = [
            'firstColumn' => $firstColumn,
            'operator' => $operator,
            'secondColumn' => $secondColumn,
            'type' => 'OR'
        ];

        return $this;
    }

    /**
     * Add a raw condition to the join
     *
     * @param string $sql Raw SQL condition
     * @return self
     */
    public function onRaw(string $sql): self
    {
        $this->conditions[] = [
            'raw' => true,
            'sql' => $sql,
            'type' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a raw OR condition to the join
     *
     * @param string $sql Raw SQL condition
     * @return self
     */
    public function orOnRaw(string $sql): self
    {
        $this->conditions[] = [
            'raw' => true,
            'sql' => $sql,
            'type' => 'OR'
        ];

        return $this;
    }

    /**
     * Get all conditions
     *
     * @return array
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }
}