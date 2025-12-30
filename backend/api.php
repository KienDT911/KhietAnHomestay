<?php
// ===== Room Management API =====
header('Content-Type: application/json');

// Database connection (using SQLite for simplicity, can be changed to MySQL)
class RoomDatabase {
    private $db;
    
    public function __construct() {
        // Create/open SQLite database
        $this->db = new PDO('sqlite:rooms.db');
        $this->createTable();
    }
    
    private function createTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price REAL NOT NULL,
                capacity INTEGER NOT NULL,
                description TEXT,
                amenities TEXT,
                status TEXT DEFAULT 'available',
                booked_until TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function getAllRooms() {
        $stmt = $this->db->query("SELECT * FROM rooms");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRoomById($id) {
        $stmt = $this->db->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function addRoom($data) {
        $stmt = $this->db->prepare("
            INSERT INTO rooms (name, price, capacity, description, amenities, status, booked_until)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['price'],
            $data['capacity'],
            $data['description'],
            json_encode($data['amenities']),
            $data['status'],
            $data['booked_until'] ?? null
        ]);
        return $this->db->lastInsertId();
    }
    
    public function updateRoom($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE rooms 
            SET name = ?, price = ?, capacity = ?, description = ?, amenities = ?, status = ?, booked_until = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['price'],
            $data['capacity'],
            $data['description'],
            json_encode($data['amenities']),
            $data['status'],
            $data['booked_until'] ?? null,
            $id
        ]);
        return true;
    }
    
    public function deleteRoom($id) {
        $stmt = $this->db->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    }
}

// Handle requests
$request = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$db = new RoomDatabase();

if ($request === 'GET' && strpos($path, '/api/rooms') !== false) {
    if (preg_match('/\/api\/rooms\/(\d+)/', $path, $matches)) {
        // Get single room
        $room = $db->getRoomById($matches[1]);
        echo json_encode($room ?? ['error' => 'Room not found']);
    } else {
        // Get all rooms
        $rooms = $db->getAllRooms();
        // Parse amenities JSON
        array_walk($rooms, function(&$room) {
            $room['amenities'] = json_decode($room['amenities'], true);
        });
        echo json_encode($rooms);
    }
} elseif ($request === 'POST' && strpos($path, '/api/rooms/sync') !== false) {
    // Sync rooms from admin
    $data = json_decode(file_get_contents('php://input'), true);
    foreach ($data as $room) {
        if ($db->getRoomById($room['id'])) {
            $db->updateRoom($room['id'], $room);
        } else {
            $db->addRoom($room);
        }
    }
    echo json_encode(['status' => 'success', 'message' => 'Rooms synchronized']);
} elseif ($request === 'POST' && strpos($path, '/api/rooms') !== false) {
    // Add room
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $db->addRoom($data);
    echo json_encode(['status' => 'success', 'id' => $id]);
} elseif ($request === 'PUT' && preg_match('/\/api\/rooms\/(\d+)/', $path, $matches)) {
    // Update room
    $data = json_decode(file_get_contents('php://input'), true);
    $db->updateRoom($matches[1], $data);
    echo json_encode(['status' => 'success']);
} elseif ($request === 'DELETE' && preg_match('/\/api\/rooms\/(\d+)/', $path, $matches)) {
    // Delete room
    $db->deleteRoom($matches[1]);
    echo json_encode(['status' => 'success']);
}
?>
