<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\Persistence;

use FP\Resv\Core\Adapters\DatabaseAdapterInterface;
use FP\Resv\Core\Exceptions\DatabaseException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;

/**
 * WordPress Reservation Repository
 * 
 * Implements ReservationRepositoryInterface using WordPress database.
 * This is the Infrastructure layer implementation.
 *
 * @package FP\Resv\Infrastructure\Persistence
 */
final class ReservationRepository implements ReservationRepositoryInterface
{
    private const TABLE_NAME = 'fp_reservations';
    private const CUSTOMERS_TABLE_NAME = 'fp_customers';
    
    public function __construct(
        private readonly DatabaseAdapterInterface $db,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Find a reservation by ID
     * 
     * @param int $id Reservation ID
     * @return Reservation|null
     */
    public function findById(int $id): ?Reservation
    {
        $table = $this->db->getTableName(self::TABLE_NAME);
        $customersTable = $this->db->getTableName(self::CUSTOMERS_TABLE_NAME);
        $query = $this->db->prepare(
            "SELECT r.*, c.first_name, c.last_name, c.email, c.phone 
             FROM {$table} r 
             LEFT JOIN {$customersTable} c ON r.customer_id = c.id 
             WHERE r.id = %d LIMIT 1",
            $id
        );
        
        $row = $this->db->getDb()->get_row($query, ARRAY_A);
        
        if ($row === null) {
            return null;
        }
        
        return Reservation::fromArray($row);
    }
    
    /**
     * Find reservations by criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array<Reservation>
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array
    {
        $table = $this->db->getTableName(self::TABLE_NAME);
        $where = [];
        $values = [];
        
        // Build WHERE clause
        if (isset($criteria['date'])) {
            $where[] = 'date = %s';
            $values[] = $criteria['date'];
        }
        
        if (isset($criteria['status'])) {
            $where[] = 'status = %s';
            $values[] = $criteria['status'];
        }
        
        if (isset($criteria['email'])) {
            $where[] = 'email = %s';
            $values[] = $criteria['email'];
        }
        
        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT * FROM {$table} {$whereClause} ORDER BY date DESC, time DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;
        
        $preparedQuery = $values !== []
            ? $this->db->prepare($query, ...$values)
            : $this->db->prepare($query, $limit, $offset);
        
        $results = $this->db->getDb()->get_results($preparedQuery, ARRAY_A);
        
        $reservations = [];
        foreach ($results as $row) {
            $reservations[] = Reservation::fromArray($row);
        }
        
        return $reservations;
    }
    
    /**
     * Save a reservation (create or update)
     * 
     * @param Reservation $reservation Reservation to save
     * @param array<string, mixed> $additionalCustomerData Optional additional customer data (marketing_consent, profiling_consent, lang)
     * @return Reservation Saved reservation with ID
     * @throws DatabaseException If save fails
     */
    public function save(Reservation $reservation, array $additionalCustomerData = []): Reservation
    {
        $table = $this->db->getTableName(self::TABLE_NAME);
        $customersTable = $this->db->getTableName(self::CUSTOMERS_TABLE_NAME);
        $data = $reservation->toArray();
        
        // Extract customer data before removing from reservation data
        // Convert empty strings to null for phone (database expects null, not empty string)
        $phone = $data['phone'] ?? '';
        $customerData = [
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => ($phone === '' || $phone === null) ? null : $phone,
            'lang' => $additionalCustomerData['customer_lang'] ?? $additionalCustomerData['lang'] ?? $data['customer_lang'] ?? $data['lang'] ?? null,
            'marketing_consent' => !empty($additionalCustomerData['marketing_consent']) ? 1 : (!empty($data['marketing_consent']) ? 1 : 0),
            'profiling_consent' => !empty($additionalCustomerData['profiling_consent']) ? 1 : (!empty($data['profiling_consent']) ? 1 : 0),
        ];
        
        // Remove ID, customer fields, and timestamp fields from reservation data
        // (these fields are managed by the database or don't exist in the table)
        $id = $data['id'] ?? null;
        
        // Define allowed fields for the reservations table
        // Only keep fields that exist in the database table
        $allowedFields = [
            'date',
            'time',
            'party',
            'meal',
            'status',
            'notes',
            'allergies',
            'customer_id',
        ];
        
        // Filter data to only include allowed fields
        $data = array_intersect_key($data, array_flip($allowedFields));
        
        // Save or update customer if we have at least email or name
        $customerId = null;
        if (!empty($customerData['email']) || !empty($customerData['first_name']) || !empty($customerData['last_name'])) {
            // saveCustomer now throws DatabaseException on failure instead of returning null
            $customerId = $this->saveCustomer($customersTable, $customerData);
        }
        
        // Set customer_id in reservation data
        // Only set customer_id if it's not null (avoid setting null if column doesn't allow it)
        if ($customerId !== null) {
            $data['customer_id'] = $customerId;
        }
        
        // Suppress HTML error output to prevent non-JSON responses
        $wpdb = $this->db->getDb();
        $originalShowErrors = $wpdb->show_errors;
        $originalSuppressErrors = $wpdb->suppress_errors;
        $wpdb->show_errors = false;
        $wpdb->suppress_errors = true;
        
        try {
            if ($id === null) {
                // Insert
                $result = $wpdb->insert($table, $data);
                
                if ($result === false) {
                    $error = $wpdb->last_error ?: 'Unknown database error';
                    $this->logger->error('Failed to insert reservation', [
                        'data' => $data,
                        'error' => $error,
                        'last_query' => $wpdb->last_query ?? 'No query',
                    ]);
                    throw new DatabaseException('Failed to create reservation: ' . $error);
                }
                
                $reservation->setId((int) $wpdb->insert_id);
            } else {
                // Update
                $result = $wpdb->update($table, $data, ['id' => $id]);
                
                if ($result === false) {
                    $error = $wpdb->last_error ?: 'Unknown database error';
                    $this->logger->error('Failed to update reservation', [
                        'id' => $id,
                        'data' => $data,
                        'error' => $error,
                        'last_query' => $wpdb->last_query ?? 'No query',
                    ]);
                    throw new DatabaseException('Failed to update reservation: ' . $error);
                }
            }
        } finally {
            // Restore original error settings
            $wpdb->show_errors = $originalShowErrors;
            $wpdb->suppress_errors = $originalSuppressErrors;
        }
        
        return $reservation;
    }
    
    /**
     * Save or update customer in customers table
     * 
     * @param string $customersTable Table name for customers
     * @param array<string, string> $customerData Customer data (first_name, last_name, email, phone)
     * @return int Customer ID
     * @throws DatabaseException If save fails
     */
    private function saveCustomer(string $customersTable, array $customerData): int
    {
        $wpdb = $this->db->getDb();
        
        // Suppress HTML error output to prevent non-JSON responses
        $originalShowErrors = $wpdb->show_errors;
        $originalSuppressErrors = $wpdb->suppress_errors;
        $wpdb->show_errors = false;
        $wpdb->suppress_errors = true;
        
        try {
            // Try to find existing customer by email if email is provided
            $existingCustomerId = null;
            if (!empty($customerData['email'])) {
                $existingCustomer = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id FROM {$customersTable} WHERE email = %s LIMIT 1",
                        $customerData['email']
                    ),
                    ARRAY_A
                );
                if ($existingCustomer !== null) {
                    $existingCustomerId = (int) $existingCustomer['id'];
                }
            }
            
            // Prepare customer data for insert/update
            // Note: first_name, last_name, email, marketing_consent, and profiling_consent are NOT NULL in the table
            $customerInsertData = [
                'first_name' => !empty($customerData['first_name']) ? $customerData['first_name'] : 'N/A',
                'last_name' => !empty($customerData['last_name']) ? $customerData['last_name'] : 'N/A',
                'email' => !empty($customerData['email']) ? $customerData['email'] : '',
                'phone' => $customerData['phone'] ?? null,
                'lang' => $customerData['lang'] ?? null,
                'marketing_consent' => !empty($customerData['marketing_consent']) ? 1 : 0,
                'profiling_consent' => !empty($customerData['profiling_consent']) ? 1 : 0,
            ];
            
            // Log customer data before insert/update for debugging
            $this->logger->debug('Saving customer data', [
                'existing_customer_id' => $existingCustomerId,
                'customer_insert_data' => $customerInsertData,
                'raw_customer_data' => $customerData,
            ]);
            
            // Validate required fields before insert/update
            if (empty($customerInsertData['email'])) {
                $this->logger->warning('Customer email is empty, will generate temporary email', [
                    'customer_data' => $customerData,
                ]);
            }
            if (empty($customerInsertData['first_name']) || $customerInsertData['first_name'] === 'N/A') {
                $this->logger->warning('Customer first_name is empty or N/A', [
                    'customer_data' => $customerData,
                ]);
            }
            if (empty($customerInsertData['last_name']) || $customerInsertData['last_name'] === 'N/A') {
                $this->logger->warning('Customer last_name is empty or N/A', [
                    'customer_data' => $customerData,
                ]);
            }
            
            if ($existingCustomerId !== null) {
                // Update existing customer
                $result = $wpdb->update(
                    $customersTable,
                    $customerInsertData,
                    ['id' => $existingCustomerId]
                );
                
                if ($result === false) {
                    $error = $wpdb->last_error ?: 'Unknown database error';
                    $this->logger->error('Failed to update customer', [
                        'customer_id' => $existingCustomerId,
                        'data' => $customerInsertData,
                        'error' => $error,
                        'last_query' => $wpdb->last_query ?? 'No query',
                    ]);
                    throw new DatabaseException('Failed to update customer: ' . $error);
                }
                
                return $existingCustomerId;
            } else {
                // Insert new customer
                // If email is empty, we can't insert (email is UNIQUE and required)
                // But we can still create a customer with a placeholder email if we have at least name
                if (empty($customerInsertData['email'])) {
                    // Generate a temporary email if we don't have one
                    $customerInsertData['email'] = 'temp_' . time() . '_' . uniqid() . '@temp.local';
                }
                
                $result = $wpdb->insert($customersTable, $customerInsertData);
                
                if ($result === false) {
                    $error = $wpdb->last_error ?: 'Unknown database error';
                    $this->logger->error('Failed to insert customer', [
                        'data' => $customerInsertData,
                        'error' => $error,
                        'last_query' => $wpdb->last_query ?? 'No query',
                    ]);
                    throw new DatabaseException('Failed to insert customer: ' . $error);
                }
                
                return (int) $wpdb->insert_id;
            }
        } finally {
            // Restore original error settings
            $wpdb->show_errors = $originalShowErrors;
            $wpdb->suppress_errors = $originalSuppressErrors;
        }
    }
    
    /**
     * Delete a reservation
     * 
     * @param int $id Reservation ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $table = $this->db->getTableName(self::TABLE_NAME);
        $result = $this->db->getDb()->delete(
            $table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Count reservations matching criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @return int Count
     */
    public function count(array $criteria): int
    {
        $table = $this->db->getTableName(self::TABLE_NAME);
        $where = [];
        $values = [];
        
        // Build WHERE clause (same logic as findBy)
        if (isset($criteria['date'])) {
            $where[] = 'date = %s';
            $values[] = $criteria['date'];
        }
        
        if (isset($criteria['status'])) {
            $where[] = 'status = %s';
            $values[] = $criteria['status'];
        }
        
        if (isset($criteria['email'])) {
            $where[] = 'email = %s';
            $values[] = $criteria['email'];
        }
        
        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT COUNT(*) FROM {$table} {$whereClause}";
        $preparedQuery = $values !== []
            ? $this->db->prepare($query, ...$values)
            : $query;
        
        return (int) $this->db->getDb()->get_var($preparedQuery);
    }
}

