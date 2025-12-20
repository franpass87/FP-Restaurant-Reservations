<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\Persistence;

use FP\Resv\Core\Adapters\DatabaseAdapterInterface;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Closures\Models\Closure;
use FP\Resv\Domain\Closures\Repositories\ClosureRepositoryInterface;
use FP\Resv\Core\Exceptions\DatabaseException;

/**
 * Closure Repository
 * 
 * Infrastructure implementation of ClosureRepositoryInterface.
 * Uses WordPress database via DatabaseAdapter.
 *
 * @package FP\Resv\Infrastructure\Persistence
 */
final class ClosureRepository implements ClosureRepositoryInterface
{
    public function __construct(
        private readonly DatabaseAdapterInterface $db,
        private readonly LoggerInterface $logger
    ) {
    }
    
    private const TABLE_NAME = 'fp_restaurant_closures';
    
    /**
     * Find a closure by ID
     * 
     * @param int $id Closure ID
     * @return Closure|null
     */
    public function findById(int $id): ?Closure
    {
        try {
            $table = $this->db->getTableName(self::TABLE_NAME);
            $query = $this->db->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $id
            );
            
            $row = $this->db->getDb()->get_row($query, ARRAY_A);
            
            if ($row === null) {
                return null;
            }
            
            return Closure::fromArray($row);
        } catch (\Exception $e) {
            $this->logger->error('Failed to find closure by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to find closure', 0, $e);
        }
    }
    
    /**
     * Find closures by criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array<Closure>
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array
    {
        try {
            $table = $this->db->getTableName(self::TABLE_NAME);
            $where = [];
            $values = [];
            
            if (isset($criteria['is_active'])) {
                $where[] = 'is_active = %d';
                $values[] = (int) $criteria['is_active'];
            }
            
            if (isset($criteria['scope'])) {
                $where[] = 'scope = %s';
                $values[] = $criteria['scope'];
            }
            
            if (isset($criteria['room_id'])) {
                $where[] = 'room_id = %d';
                $values[] = (int) $criteria['room_id'];
            }
            
            if (isset($criteria['table_id'])) {
                $where[] = 'table_id = %d';
                $values[] = (int) $criteria['table_id'];
            }
            
            if (isset($criteria['start_date_from'])) {
                $where[] = 'start_date >= %s';
                $values[] = $criteria['start_date_from'];
            }
            
            if (isset($criteria['start_date_to'])) {
                $where[] = 'start_date <= %s';
                $values[] = $criteria['start_date_to'];
            }
            
            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $query = "SELECT * FROM {$table} {$whereClause} ORDER BY start_date ASC LIMIT %d OFFSET %d";
            $values[] = $limit;
            $values[] = $offset;
            
            $preparedQuery = $values !== []
                ? $this->db->prepare($query, ...$values)
                : $this->db->prepare($query, $limit, $offset);
            
            $results = $this->db->getDb()->get_results($preparedQuery, ARRAY_A);
            
            return array_map(
                fn(array $row) => Closure::fromArray($row),
                $results
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to find closures', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to find closures', 0, $e);
        }
    }
    
    /**
     * Save a closure (create or update)
     * 
     * @param Closure $closure Closure to save
     * @return Closure Saved closure with ID
     */
    public function save(Closure $closure): Closure
    {
        try {
            $table = $this->db->getTableName(self::TABLE_NAME);
            $data = $closure->toArray();
            
            // Remove ID from data for insert
            $id = $data['id'];
            unset($data['id']);
            
            if ($id === null) {
                // Insert
                $result = $this->db->getDb()->insert($table, $data);
                
                if ($result === false) {
                    $this->logger->error('Failed to insert closure', [
                        'data' => $data,
                        'error' => $this->db->getDb()->last_error,
                    ]);
                    throw new DatabaseException('Failed to create closure');
                }
                
                $closure->setId((int) $this->db->getDb()->insert_id);
                
                $this->logger->info('Closure created', [
                    'id' => $closure->getId(),
                    'title' => $closure->getTitle(),
                ]);
            } else {
                // Update
                $result = $this->db->getDb()->update(
                    $table,
                    $data,
                    ['id' => $id]
                );
                
                if ($result === false) {
                    $this->logger->error('Failed to update closure', [
                        'id' => $id,
                        'data' => $data,
                        'error' => $this->db->getDb()->last_error,
                    ]);
                    throw new DatabaseException('Failed to update closure');
                }
                
                $this->logger->info('Closure updated', [
                    'id' => $id,
                    'title' => $closure->getTitle(),
                ]);
            }
            
            return $closure;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save closure', [
                'closure_id' => $closure->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to save closure', 0, $e);
        }
    }
    
    /**
     * Delete a closure
     * 
     * @param int $id Closure ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        try {
            $table = $this->db->getTableName(self::TABLE_NAME);
            $result = $this->db->getDb()->delete(
                $table,
                ['id' => $id],
                ['%d']
            );
            
            if ($result !== false) {
                $this->logger->info('Closure deleted', ['id' => $id]);
            }
            
            return $result !== false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete closure', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to delete closure', 0, $e);
        }
    }
}



