<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Listing\Dao;


use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineQueryBuilder;
use Pimcore\Db\ZendCompatibility\Expression;
use Pimcore\Db\ZendCompatibility\QueryBuilder as ZendCompatibilityQueryBuilder;

trait QueryBuilderHelperTrait
{
    /**
     * @var callable|null
     */
    protected $onCreateQueryBuilderCallback;

    /**
     * @param callable|null $callback
     */
    public function onCreateQueryBuilder(?callable $callback): void
    {
        $this->onCreateQueryBuilderCallback = $callback;
    }

    protected function applyListingParametersToQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $this->applyConditionsToQueryBuilder($queryBuilder);
        $this->applyGroupByToQueryBuilder($queryBuilder);
        $this->applyOrderByToQueryBuilder($queryBuilder);
        $this->applyLimitToQueryBuilder($queryBuilder);

        $callback = $this->onCreateQueryBuilderCallback;
        if(is_callable($callback)) {
            $callback($queryBuilder);
        }
    }

    private function applyConditionsToQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $condition = $this->model->getCondition();

        if ($condition) {
            $queryBuilder->where($condition);
        }
    }

    private function applyGroupByToQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $groupBy = $this->model->getGroupBy();
        if ($groupBy) {
            $queryBuilder->addGroupBy($groupBy);
        }
    }

    private function applyOrderByToQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $orderKey = $this->model->getOrderKey();
        $order = $this->model->getOrder();

        if (!empty($order) || !empty($orderKey)) {
            $c = 0;
            $lastOrder = $order[0] ?? null;

            if (is_array($orderKey)) {
                foreach ($orderKey as $key) {
                    if (!empty($order[$c])) {
                        $lastOrder = $order[$c];
                    }

                    $queryBuilder->addOrderBy($key, $lastOrder);

                    $c++;
                }
            }
        }
    }

    private function applyLimitToQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->setFirstResult($this->model->getOffset());
        $queryBuilder->setMaxResults($this->model->getLimit());
    }

    private function getConditionParametersArray(): array
    {

    }

    /**
     * @internal
     * @deprecated
     * @param @param array|string|Expression $columns $columns
     * @return ZendCompatibilityQueryBuilder|QueryBuilder
     */
    protected function getQueryBuilderCompatibility($columns = '*')
    {
        if(!is_callable($this->onCreateQueryCallback)) {
            // use Doctrine query builder (default)
            return $this->getQueryBuilder(...$columns);
        } else {
            // use deprecated ZendCompatibility\QueryBuilder
            return $this->getQuery($columns);
        }
    }

    protected function prepareQueryBuilderForTotalCount($queryBuilder): void
    {
        if($queryBuilder instanceof DoctrineQueryBuilder) {
            $queryBuilder->select('COUNT(*)');
            $queryBuilder->resetQueryPart('orderBy');
            $queryBuilder->setMaxResults(null);
            $queryBuilder->setFirstResult(0);
        } elseif ($queryBuilder instanceof ZendCompatibilityQueryBuilder) {
            $queryBuilder->columns([new Expression('COUNT(*)')]);
            $queryBuilder->reset(ZendCompatibilityQueryBuilder::LIMIT_COUNT);
            $queryBuilder->reset(ZendCompatibilityQueryBuilder::LIMIT_OFFSET);
            $queryBuilder->reset(ZendCompatibilityQueryBuilder::ORDER);
        }
    }
}
