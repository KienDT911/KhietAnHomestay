<?php
/**
 * MongoDB Homepage API - Read-Only Access
 * Used by: Public Website (Homepage)
 * Features: Read rooms, fetch availability status only
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'vendor/autoload.php';

use MongoDB\Client;
use MongoDB\Exception\Exception as MongoException;

class MongoHomepageAPI {
    private $client;
    private $db;
    private $collection;
    
    public function __construct() {
        try {
            $connectionString = $_ENV['MONGODB_URI'] ?? 'mongodb://localhost:27017/';
            $this->client = new Client($connectionString);
            $this->db = $this->client[($_ENV['MONGODB_DB'] ?? 'khiet_an_homestay')];
            $this->collection = $this->db[($_ENV['MONGODB_COLLECTION'] ?? 'rooms')];
            
        } catch (MongoException $e) {
            $this->error('Database connection failed', 500);
        }
    }
    
    public function getAllRooms() {
        try {
            $rooms = $this->collection->find([], ['projection' => ['_id' => 0]])->toArray();
            
            // Format rooms for public display
            $formattedRooms = array_map(function($room) {
                return [
                    'room_id' => $room['room_id'] ?? null,
                    'name' => $room['name'] ?? '',
                    'price' => $room['price'] ?? 0,
                    'capacity' => $room['capacity'] ?? 1,
                    'description' => $room['description'] ?? '',
                    'amenities' => $room['amenities'] ?? [],
                    'status' => $room['status'] ?? 'available',
                    'available' => ($room['status'] ?? 'available') === 'available'
                ];
            }, array_values($rooms));
            
            $this->success($formattedRooms);
            
        } catch (MongoException $e) {
            $this->error('Failed to fetch rooms', 500);
        }
    }
    
    public function getRoomById($room_id) {
        try {
            $room = $this->collection->findOne(['room_id' => (int)$room_id], ['projection' => ['_id' => 0]]);
            
            if ($room) {
                $formattedRoom = [
                    'room_id' => $room['room_id'] ?? null,
                    'name' => $room['name'] ?? '',
                    'price' => $room['price'] ?? 0,
                    'capacity' => $room['capacity'] ?? 1,
                    'description' => $room['description'] ?? '',
                    'amenities' => $room['amenities'] ?? [],
                    'status' => $room['status'] ?? 'available',
                    'available' => ($room['status'] ?? 'available') === 'available'
                ];
                $this->success($formattedRoom);
            } else {
                $this->error('Room not found', 404);
            }
            
        } catch (MongoException $e) {
            $this->error('Failed to fetch room', 500);
        }
    }
    
    public function getRoomStatus($room_id) {
        try {
            $room = $this->collection->findOne(
                ['room_id' => (int)$room_id],
                ['projection' => ['room_id' => 1, 'name' => 1, 'status' => 1, 'booked_until' => 1]]
            );
            
            if ($room) {
                $this->success([
                    'room_id' => $room['room_id'],
                    'name' => $room['name'],
                    'status' => $room['status'],
                    'available' => $room['status'] === 'available',
                    'booked_until' => $room['booked_until'] ?? null
                ]);
            } else {
                $this->error('Room not found', 404);
            }
            
        } catch (MongoException $e) {
            $this->error('Failed to fetch room status', 500);
        }
    }
    
    public function getAvailableRooms() {
        try {
            $rooms = $this->collection->find(
                ['status' => 'available'],
                ['projection' => ['_id' => 0]]
            )->toArray();
            
            $formattedRooms = array_map(function($room) {
                return [
                    'room_id' => $room['room_id'] ?? null,
                    'name' => $room['name'] ?? '',
                    'price' => $room['price'] ?? 0,
                    'capacity' => $room['capacity'] ?? 1,
                    'amenities' => $room['amenities'] ?? []
                ];
            }, array_values($rooms));
            
            $this->success($formattedRooms);
            
        } catch (MongoException $e) {
            $this->error('Failed to fetch available rooms', 500);
        }
    }
    
    private function success($data) {
        http_response_code(200);
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

$api = new MongoHomepageAPI();

// Handle CORS preflight
if ($request === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Read-only routes
if ($request === 'GET') {
    if (preg_match('/\/api\/rooms\/(\d+)\/status/', $path, $matches)) {
        $api->getRoomStatus($matches[1]);
    } elseif (preg_match('/\/api\/rooms\/(\d+)/', $path, $matches)) {
        $api->getRoomById($matches[1]);
    } elseif (preg_match('/\/api\/rooms\/available/', $path)) {
        $api->getAvailableRooms();
    } elseif (preg_match('/\/api\/rooms/', $path)) {
        $api->getAllRooms();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Homepage API is read-only.']);
}
?>
