<?php

namespace Maharlika\Facades;

/**
 * @method static table(string $table, ?\Maharlika\Database\Model $model = null)
 * @method static select(array|string|\Maharlika\Database\RawExpression $columns = ['*'])
 * @method static addSelect(array|string $columns)
 * @method static where(string|\Closure|array|\Maharlika\Database\RawExpression $column, mixed $operator = null, mixed $value = null)
 * @method static orWhere(string|\Closure|array|\Maharlika\Database\RawExpression $column, mixed $operator = null, mixed $value = null)
 * @method static whereRaw(string $sql, array $bindings = []) Add a raw WHERE clause
 * @method static orWhereRaw(string $sql, array $bindings = []) Add a raw OR WHERE clause
 * @method static whereIn(string $column, array $values)
 * @method static orWhereIn(string $column, array $values)
 * @method static whereNotIn(string $column, array $values)
 * @method static orWhereNotIn(string $column, array $values)
 * @method static whereNull(string $column)
 * @method static orWhereNull(string $column)
 * @method static whereNotNull(string $column)
 * @method static orWhereNotNull(string $column)
 * @method static whereBetween(string $column, array $values)
 * @method static orWhereBetween(string $column, array $values)
 * @method static whereNotBetween(string $column, mixed $min, mixed $max)
 * @method static orWhereNotBetween(string $column, mixed $min, mixed $max)
 * @method static whereLike(string $column, string $value)
 * @method static orWhereLike(string $column, string $value)
 * @method static whereNotLike(string $column, string $value)
 * @method static orWhereNotLike(string $column, string $value)
 * @method static whereColumn(string $first, ?string $operator = null, ?string $second = null)
 * @method static orWhereColumn(string $first, ?string $operator = null, ?string $second = null)
 * @method static whereAny(array $columns, mixed $operator = null, mixed $value = null)
 * @method static orWhereAny(array $columns, mixed $operator = null, mixed $value = null)
 * @method static whereAll(array $columns, mixed $operator = null, mixed $value = null)
 * @method static whereAnyNull(array $columns)
 * @method static whereAnyNotNull(array $columns)
 * @method static whereDate(string $column, string|null $operator = null, \DateTimeInterface|string|null $value = null)
 * @method static orWhereDate(string $column, mixed $operator = null, mixed $value = null)
 * @method static whereYear(string $column, mixed $operator = null, mixed $value = null)
 * @method static orWhereYear(string $column, mixed $operator = null, mixed $value = null)
 * @method static whereMonth(string $column, mixed $operator = null, mixed $value = null)
 * @method static orWhereMonth(string $column, mixed $operator = null, mixed $value = null)
 * @method static whereDay(string $column, mixed $operator = null, mixed $value = null)
 * @method static orWhereDay(string $column, mixed $operator = null, mixed $value = null)
 * @method static whereTime(string $column, mixed $operator = null, mixed $value = null)
 * @method static orWhereTime(string $column, mixed $operator = null, mixed $value = null)
 * @method static join(string $table, ?string $first = null, ?string $operator = null, ?string $second = null, string $type = 'inner')
 * @method static leftJoin(string $table, ?string $first = null, ?string $operator = null, ?string $second = null)
 * @method static rightJoin(string $table, ?string $first = null, ?string $operator = null, ?string $second = null)
 * @method static crossJoin(string $table)
 * @method static orderBy(string $column, string $direction = 'asc')
 * @method static latest(string $column = 'created_at')
 * @method static oldest(string $column = 'created_at')
 * @method static \Maharlika\Database\Collection map(callable $callback)
 * @method static limit(int $value)
 * @method static take(int $value)
 * @method static offset(int $value)
 * @method static skip(int $value)
 * @method static \Maharlika\Database\Collection<int, \stdClass> get(array|string $columns = ['*'])
 * @method static stdClass|null first()
 * @method static array find(mixed $id)
 * @method static array findOrFail(mixed $id)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static int count()
 * @method static array pluck(string $column, ?string $key = null)
 * @method static bool update(array $values)
 * @method static bool insert(array $values)
 * @method static bool delete()
 * @method static bool save(array $values)  
 * @method static \Maharlika\Database\RawExpression raw(string $value) Create a raw SQL expression
 * @method static void enableQueryLog(?string $name = null) Enable query logging
 * @method static void disableQueryLog(?string $name = null) Disable query logging
 * @method static array getQueryLog(?string $name = null) Get query log
 * @method static void flushQueryLog(?string $name = null) Flush query log
 * @method static void enableQueryLogOnAll() Enable query logging on all connections
 * @method static void disableQueryLogOnAll() Disable query logging on all connections
 * @method static array getAllQueryLogs() Get query logs from all connections
 * @method static void flushAllQueryLogs() Flush query logs from all connections
 * 
 * @see Maharlika\Database\DatabaseManager
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'db';
    }
}