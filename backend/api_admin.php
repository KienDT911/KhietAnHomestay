<?php
/**
 * MongoDB Admin API - Full CRUD Operations
 * Used by: Admin Panel only
 * Features: Create, Read, Update, Delete rooms
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'vendor/autoload.php';

use MongoDB\Client;
use MongoDB\Exception\Exception as MongoException;

class MongoAdminAPI {
    private $client;
    private $db;
    private $collection;
    
    public function __construct() {
        try {
            $connectionString = $_ENV['MONGODB_URI'] ?? 'mongodb://localhost:27017/';
            $this->client = new Client($connectionString);
            $this->db = $this->client[($_ENV['MONGODB_DB'] ?? 'khiet_an_homestay')];
            $this->collection = $this->db[($_ENV['MONGODB_COLLECTION'] ?? 'rooms')];
            
            // Create index
            $this->collection->createIndex(['room_id' => 1], ['unique' => true, 'sparse' => true]);
            
        } catch (MongoException $e) {
            $this->error('Database connection failed: ' . $e->getMessage(), 500);
        }
    }
    
    public function getAllRooms() {
        try {
            $rooms = $this->collection->find([], ['projection' => ['_id' => 0]])->toArray();
            $this->success(array_values($rooms));
        } catch (MongoException $e) {
            $this->error('Failed to fetch rooms: ' . $e->getMessage(), 500);
        }
    }
    
    public function getRoomById($room_id) {
        try {
            $room = $this->collection->findOne(['room_id' => (int)$room_id], ['projection' => ['_id' => 0]]);
            if ($room) {
                $this->success($room);
            } else {
                $this->error('Room not found', 404);
            }
        } catch (MongoException $e) {
            $this->error('Failed to fetch room: ' . $e->getMessage(), 500);
        }
    }
    
    public function addRoom($data) {
        try {
            // Validate required fields
            if (empty($data['name']) || empty($data['price']) || empty($data['capacity'])) {
                $this->error('Missing required fields: name, price, capacity', 400);
                return;
            }
            
            // Get next room_id
            $lastRoom = $this->collection->findOne([], ['sort' => ['room_id' => -1], 'projection' => ['room_id' => 1]]);
            $nextId = $lastRoom ? $lastRoom['room_id'] + 1 : 1;
            
            $roomData = [
                'room_id' => $nextId,
                'name' => $data['name'],
                'price' => (float)$data['price'],
                'capacity' => (int)$data['capacity'],
                'description' => $data['description'] ?? '',
                'amenities' => $data['amenities'] ?? [],
                'image_url' => $data['image_url'] ?? '',
                'status' => $data['status'] ?? 'available',
                'bookings' => $data['bookings'] ?? [],
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $this->collection->insertOne($roomData);
            $this->success(['status' => 'success', 'room_id' => $nextId, 'inserted_id' => (string)$result->getInsertedId()], 201);
            
        } catch (MongoException $e) {
            $this->error('Failed to add room: ' . $e->getMessage(), 500);
        }
    }
    
    public function updateRoom($room_id, $data) {
        try {
            $updateData = ['updated_at' => new \MongoDB\BSON\UTCDateTime()];
            
            // Only update provided fields
            $allowedFields = ['name', 'price', 'capacity', 'description', 'amenities', 'status', 'image_url', 'bookings'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'price') {
                        $updateData[$field] = (float)$data[$field];
                    } elseif ($field === 'capacity') {
                        $updateData[$field] = (int)$data[$field];
                    } else {
                        $updateData[$field] = $data[$field];
                    }
                }
            }
            
            $result = $this->collection->updateOne(
                ['room_id' => (int)$room_id],
                ['$set' => $updateData]
            );
            
            if ($result->getMatchedCount() > 0) {
                $room = $this->collection->findOne(['room_id' => (int)$room_id], ['projection' => ['_id' => 0]]);
                $this->success(['status' => 'success', 'room' => $room]);
            } else {
                $this->error('Room not found', 404);
            }
            
        } catch (MongoException $e) {
            $this->error('Failed to update room: ' . $e->getMessage(), 500);
        }
    }
    
    public function deleteRoom($room_id) {
        try {
            $result = $this->collection->deleteOne(['room_id' => (int)$room_id]);
            
            if ($result->getDeletedCount() > 0) {
                $this->success(['status' => 'success', 'deleted' => true]);
            } else {
                $this->error('Room not found', 404);
            }
            
        } catch (MongoException $e) {
            $this->error('Failed to delete room: ' . $e->getMessage(), 500);
        }
    }
    
    public function getStats() {
        try {
            $total = $this->collection->countDocuments([]);
            $available = $this->collection->countDocuments(['status' => 'available']);
            $booked = $this->collection->countDocuments(['status' => 'booked']);
            $maintenance = $this->collection->countDocuments(['status' => 'maintenance']);
            
            $this->success([
                'total' => $total,
                'available' => $available,
                'booked' => $booked,
                'maintenance' => $maintenance
            ]);
            
        } catch (MongoException $e) {
            $this->error('Failed to get stats: ' . $e->getMessage(), 500);
        }
    }
    
    public function getRoomBookings($room_id) {
        try {
            $room = $this->collection->findOne(
                ['room_id' => (int)$room_id],
                ['projection' => ['bookings' => 1]]
            );
            
            if ($room) {
                $this->success($room['bookings'] ?? []);
            } else {
                $this->error('Room not found', 404);
            }
        } catch (MongoException $e) {
            $this->error('Failed to fetch bookings: ' . $e->getMessage(), 500);
        }
    }
    
    public function addBooking($room_id, $data) {
        try {
            if (empty($data['guest_name']) || empty($data['check_in']) || empty($data['check_out'])) {
                $this->error('Missing required fields: guest_name, check_in, check_out', 400);
                return;
            }
            
            $booking = [
                'booking_id' => $data['booking_id'] ?? uniqid(),
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? '',
                'guest_phone' => $data['guest_phone'] ?? '',
                'check_in' => $data['check_in'],  // ISO datetime string
                'check_out' => $data['check_out'],  // ISO datetime string
                'number_of_guests' => (int)($data['number_of_guests'] ?? 1),
                'total_price' => (float)($data['total_price'] ?? 0),
                'status' => $data['status'] ?? 'confirmed',
                'notes' => $data['notes'] ?? '',
                'created_at' => new \MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $this->collection->updateOne(
                ['room_id' => (int)$room_id],
                [
                    '$push' => ['bookings' => $booking],
                    '$set' => ['updated_at' => new \MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            if ($result->getMatchedCount() > 0) {
                $this->success(['status' => 'success', 'booking_id' => $booking['booking_id']], 201);
            } else {
                $this->error('Room not found', 404);
            }
            
        } catch (MongoException $e) {
            $this->error('Failed to add booking: ' . $e->getMessage(), 500);
        }
    }
    
    public function updateBooking($room_id, $booking_id, $data) {
        try {
            $updateData = ['updated_at' => new \MongoDB\BSON\UTCDateTime()];
            $setData = [];
            
            $allowedFields = ['guest_name', 'guest_email', 'guest_phone', 'check_in', 'check_out', 'number_of_guests', 'total_price', 'status', 'notes'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setData["bookings.$.{$field}"] = $field === 'number_of_guests' ? (int)$data[$field] : 
                                                      ($field === 'total_price' ? (float)$data[$field] : $data[$field]);
                }
            }
            
            $setData['updated_at'] = new \MongoDB\BSON\UTCDateTime();
            
            $result = $this->collection->updateOne(
                ['room_id' => (int)$room_id, 'bookings.booking_id' => $booking_id],
                ['\$set' => $setData]
            );
            
            if ($result->getMatchedCount() > 0) {
                $this->success(['status' => 'success', 'modified' => true]);
            } else {
                $this->error('Room or booking not found', 404);
            }
            
        } catch (MongoException $e) {
            $this->error('Failed to update booking: ' . $e->getMessage(), 500);
        }
    }
    
    public function deleteBooking($room_id, $booking_id) {
        try {
            $result = $this->collection->updateOne(
                ['room_id' => (int)$room_id],
                [
                    '$pull' => ['bookings' => ['booking_id' => $booking_id]],
                    '$set' => ['updated_at' => new \MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            if ($result->getModifiedCount() > 0) {
                $this->success(['status' => 'success', 'deleted' => true]);
            } else {
                $this->error('Room or booking not found', 404);
            }
            
        } catch (MongoException $e) {
            $this->error('Failed to delete booking: ' . $e->getMessage(), 500);
        }
    }
    
    private function success($data, $code = 200) {
        http_response_code($code);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    private function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

// Load environment variables
if (file_exists('.env')) {
    $env = parse_ini_file('.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Route requests
$request = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$api = new MongoAdminAPI();

// Handle CORS preflight
if ($request === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Routes
if ($request === 'GET') {
    if (preg_match('/\/api\/admin\/rooms\/(\d+)\/bookings/', $path, $matches)) {
        $api->getRoomBookings($matches[1]);
    } elseif (preg_match('/\/api\/admin\/rooms\/(\d+)/', $path, $matches)) {
        $api->getRoomById($matches[1]);
    } elseif (preg_match('/\/api\/admin\/rooms\/stats/', $path)) {
        $api->getStats();
    } elseif (preg_match('/\/api\/admin\/rooms/', $path)) {
        $api->getAllRooms();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
} elseif ($request === 'POST') {
    if (preg_match('/\/api\/admin\/rooms\/(\d+)\/bookings/', $path, $matches)) {
        $data = json_decode(file_get_contents('php://input'), true);
        $api->addBooking($matches[1], $data);
    } elseif (preg_match('/\/api\/admin\/rooms/', $path)) {
        $data = json_decode(file_get_contents('php://input'), true);
        $api->addRoom($data);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
} elseif ($request === 'PUT') {
    if (preg_match('/\/api\/admin\/rooms\/(\d+)\/bookings\/(.+)/', $path, $matches)) {
        $data = json_decode(file_get_contents('php://input'), true);
        $api->updateBooking($matches[1], $matches[2], $data);
    } elseif (preg_match('/\/api\/admin\/rooms\/(\d+)/', $path, $matches)) {
        $data = json_decode(file_get_contents('php://input'), true);
        $api->updateRoom($matches[1], $data);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
} elseif ($request === 'DELETE') {
    if (preg_match('/\/api\/admin\/rooms\/(\d+)\/bookings\/(.+)/', $path, $matches)) {
        $api->deleteBooking($matches[1], $matches[2]);
    } elseif (preg_match('/\/api\/admin\/rooms\/(\d+)/', $path, $matches)) {
        $api->deleteRoom($matches[1]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
?>
