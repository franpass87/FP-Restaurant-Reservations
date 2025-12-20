<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\Persistence;

use FP\Resv\Core\Adapters\DatabaseAdapterInterface;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Events\Models\Event;
use FP\Resv\Domain\Events\Repositories\EventRepositoryInterface;
use FP\Resv\Core\Exceptions\DatabaseException;

/**
 * Event Repository
 * 
 * Infrastructure implementation of EventRepositoryInterface.
 * Uses WordPress database via DatabaseAdapter.
 *
 * @package FP\Resv\Infrastructure\Persistence
 */
final class EventRepository implements EventRepositoryInterface
{
    public function __construct(
        private readonly DatabaseAdapterInterface $db,
        private readonly LoggerInterface $logger
    ) {
    }
    
    private const TABLE_NAME = 'fp_restaurant_events';
    
    /**
     * Find an event by ID
     * 
     * @param int $id Event ID
     * @return Event|null
     */
    public function findById(int $id): ?Event
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
            
            return Event::fromArray($row);
        } catch (\Exception $e) {
            $this->logger->error('Failed to find event by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to find event', 0, $e);
        }
    }
    
    /**
     * Find events by criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array<Event>
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
                fn(array $row) => Event::fromArray($row),
                $results
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to find events', 0, $e);
        }
    }
    
    /**
     * Save an event (create or update)
     * 
     * @param Event $event Event to save
     * @return Event Saved event with ID
     */
    public function save(Event $event): Event
    {
        try {
            $table = $this->db->getTableName(self::TABLE_NAME);
            $data = $event->toArray();
            
            // Remove ID from data for insert
            $id = $data['id'];
            unset($data['id']);
            
            if ($id === null) {
                // Insert
                $result = $this->db->getDb()->insert($table, $data);
                
                if ($result === false) {
                    $this->logger->error('Failed to insert event', [
                        'data' => $data,
                        'error' => $this->db->getDb()->last_error,
                    ]);
                    throw new DatabaseException('Failed to create event');
                }
                
                $event->setId((int) $this->db->getDb()->insert_id);
                
                $this->logger->info('Event created', [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                ]);
            } else {
                // Update
                $result = $this->db->getDb()->update(
                    $table,
                    $data,
                    ['id' => $id]
                );
                
                if ($result === false) {
                    $this->logger->error('Failed to update event', [
                        'id' => $id,
                        'data' => $data,
                        'error' => $this->db->getDb()->last_error,
                    ]);
                    throw new DatabaseException('Failed to update event');
                }
                
                $this->logger->info('Event updated', [
                    'id' => $id,
                    'title' => $event->getTitle(),
                ]);
            }
            
            return $event;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save event', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to save event', 0, $e);
        }
    }
    
    /**
     * Delete an event
     * 
     * @param int $id Event ID
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
                $this->logger->info('Event deleted', ['id' => $id]);
            }
            
            return $result !== false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete event', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new DatabaseException('Failed to delete event', 0, $e);
        }
    }
}



