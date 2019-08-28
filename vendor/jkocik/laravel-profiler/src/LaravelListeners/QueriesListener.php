<?php

namespace JKocik\Laravel\Profiler\LaravelListeners;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use JKocik\Laravel\Profiler\Contracts\LaravelListener;

class QueriesListener implements LaravelListener
{
    /**
     * @var array
     */
    protected $queries = [];

    /**
     * @var int
     */
    protected $count = 0;

    /**
     * @return void
     */
    public function listen(): void
    {
        $this->listenQueries();
        $this->listenTransactions();
    }

    /**
     * @return Collection
     */
    public function queries(): Collection
    {
        return Collection::make($this->queries);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return void
     */
    protected function listenQueries(): void
    {
        Event::listen(QueryExecuted::class, function (QueryExecuted $event) {
            $this->count++;

            list($bindings, $bindingsQuoted) = $this->formatBindings($event);

            array_push($this->queries, [
                'query',
                $event->sql,
                $event->time,
                $event->connection->getDatabaseName(),
                $event->connectionName,
                $bindings,
                $bindingsQuoted,
            ]);
        });
    }

    /**
     * @return void
     */
    protected function listenTransactions(): void
    {
        Event::listen(TransactionBeginning::class, function (TransactionBeginning $event) {
            array_push($this->queries, [
                'transaction-begin',
                $event->connection->getDatabaseName(),
                $event->connectionName,
            ]);
        });

        Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) {
            array_push($this->queries, [
                'transaction-commit',
                $event->connection->getDatabaseName(),
                $event->connectionName,
            ]);
        });

        Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $event) {
            array_push($this->queries, [
                'transaction-rollback',
                $event->connection->getDatabaseName(),
                $event->connectionName,
            ]);
        });
    }

    /**
     * @param QueryExecuted $event
     * @return array
     */
    protected function formatBindings(QueryExecuted $event): array
    {
        foreach ($event->bindings as $key => $binding) {
            $bindings[$key] = $this->truncate($binding);
            $bindingsQuoted[$key] = $this->quote($event, $bindings[$key]);
        }

        return [
            $bindings ?? [],
            $bindingsQuoted ?? [],
        ];
    }

    /**
     * @param $binding
     * @return mixed
     */
    protected function truncate($binding)
    {
        if (is_string($binding) && strlen($binding) > 255) {
            return substr($binding, 0, 255) . '...{truncated}';
        }

        return $binding;
    }

    /**
     * @param QueryExecuted $event
     * @param $binding
     * @return mixed
     */
    protected function quote(QueryExecuted $event, $binding)
    {
        if (is_int($binding) || is_float($binding)) {
            return $binding;
        }

        if (is_bool($binding)) {
            return (int) $binding;
        }

        if (is_object($binding)) {
            return '{object}';
        }

        return $event->connection->getPdo()->quote($binding);
    }
}
