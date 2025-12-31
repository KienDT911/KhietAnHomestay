/**
 * Homepage Room Management - Read-Only Access
 * Fetches room data from the public API
 */

const HOMEPAGE_API_URL = '/backend/api/rooms';

class HomepageRoomManager {
    constructor() {
        this.rooms = [];
        this.initialized = false;
    }

    /**
     * Load all rooms from the homepage API
     */
    async loadRooms() {
        try {
            const response = await fetch(HOMEPAGE_API_URL);
            const result = await response.json();
            
            if (result.success) {
                this.rooms = result.data || [];
                console.log(`✓ Loaded ${this.rooms.length} rooms`);
                this.initialized = true;
                return this.rooms;
            } else {
                console.error('Failed to load rooms:', result.error);
                return [];
            }
        } catch (error) {
            console.error('Error loading rooms:', error);
            return [];
        }
    }

    /**
     * Get a single room by ID
     */
    async getRoomById(roomId) {
        try {
            const response = await fetch(`${HOMEPAGE_API_URL}/${roomId}`);
            const result = await response.json();
            
            if (result.success) {
                return result.data;
            } else {
                console.error('Failed to load room:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error loading room:', error);
            return null;
        }
    }

    /**
     * Get only available rooms
     */
    async getAvailableRooms() {
        try {
            const response = await fetch(`${HOMEPAGE_API_URL}/available`);
            const result = await response.json();
            
            if (result.success) {
                return result.data || [];
            } else {
                console.error('Failed to load available rooms:', result.error);
                return [];
            }
        } catch (error) {
            console.error('Error loading available rooms:', error);
            return [];
        }
    }

    /**
     * Get room status (availability)
     */
    async getRoomStatus(roomId) {
        try {
            const response = await fetch(`${HOMEPAGE_API_URL}/${roomId}/status`);
            const result = await response.json();
            
            if (result.success) {
                return result.data;
            } else {
                console.error('Failed to load room status:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error loading room status:', error);
            return null;
        }
    }

    /**
     * Check if a specific room is available
     */
    isAvailable(roomId) {
        const room = this.rooms.find(r => r.room_id === roomId);
        return room ? room.available : false;
    }

    /**
     * Get all rooms cached in memory
     */
    getAllRooms() {
        return this.rooms;
    }

    /**
     * Get available rooms from cache
     */
    getAvailableFromCache() {
        return this.rooms.filter(r => r.available || r.status === 'available');
    }
}

// Initialize room manager
const homepageRoomManager = new HomepageRoomManager();

// Load rooms when page loads
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Loading rooms from server...');
    await homepageRoomManager.loadRooms();
    console.log('Rooms loaded successfully');
});

/**
 * Display rooms in the rooms section
 * Call this after rooms are loaded
 */
async function displayRoomsFromAPI() {
    const roomsGrid = document.querySelector('.rooms-grid');
    if (!roomsGrid) return;

    const rooms = homepageRoomManager.getAllRooms();
    
    if (rooms.length === 0) {
        roomsGrid.innerHTML = '<p>Loading rooms...</p>';
        return;
    }

    roomsGrid.innerHTML = '';

    rooms.forEach(room => {
        const statusBadgeClass = room.available ? 'available' : 'booked';
        const statusText = room.available ? 'Available' : 'Booked';
        const amenitiesHtml = room.amenities
            ? room.amenities.map(a => `<span class="amenity">• ${a}</span>`).join('')
            : '';

        const roomCard = document.createElement('div');
        roomCard.className = 'room-card';
        roomCard.innerHTML = `
            <div class="room-image-placeholder">
                <span class="placeholder-text">${room.name}</span>
            </div>
            <div class="room-content">
                <h3 class="room-title">${room.name}</h3>
                <p class="room-description">${room.description}</p>
                <div class="room-amenities">
                    ${amenitiesHtml}
                </div>
                <div class="room-footer">
                    <span class="room-price">$${room.price}/night</span>
                    <button class="btn-secondary" ${room.available ? '' : 'disabled'}>
                        ${room.available ? 'Book Now' : 'Not Available'}
                    </button>
                </div>
            </div>
        `;
        roomsGrid.appendChild(roomCard);
    });
}

/**
 * Update room availability status for a specific room
 * Useful for real-time updates
 */
async function updateRoomAvailability(roomId) {
    const status = await homepageRoomManager.getRoomStatus(roomId);
    
    if (status) {
        const roomIndex = homepageRoomManager.rooms.findIndex(r => r.room_id === roomId);
        if (roomIndex > -1) {
            homepageRoomManager.rooms[roomIndex].status = status.status;
            homepageRoomManager.rooms[roomIndex].available = status.available;
            console.log(`✓ Updated room ${roomId} availability: ${status.status}`);
        }
    }
}

/**
 * Poll for room availability changes (optional)
 * Useful if you want real-time updates
 */
function startAvailabilityPolling(interval = 30000) {
    setInterval(async function() {
        await homepageRoomManager.loadRooms();
        displayRoomsFromAPI();
        console.log('✓ Room availability updated');
    }, interval);
}

/**
 * Example: Check if booking should be enabled for a room
 */
function canBookRoom(roomId) {
    return homepageRoomManager.isAvailable(roomId);
}

/**
 * Example: Get room details for display
 */
function getRoomDetails(roomId) {
    const room = homepageRoomManager.getAllRooms().find(r => r.room_id === roomId);
    return room ? {
        name: room.name,
        price: room.price,
        capacity: room.capacity,
        available: room.available,
        amenities: room.amenities
    } : null;
}
