// ===== Website Room Availability Sync =====

class WebsiteRoomSync {
    constructor() {
        this.rooms = [];
        this.loadRooms();
        this.startAutoSync();
    }

    // Load rooms from admin data
    loadRooms() {
        const stored = localStorage.getItem('khietanRooms');
        if (stored) {
            this.rooms = JSON.parse(stored);
            this.updateRoomDisplay();
        }
    }

    // Auto-sync every 30 seconds to check for updates from admin
    startAutoSync() {
        setInterval(() => {
            this.loadRooms();
        }, 30000);
    }

    // Update room display on website
    updateRoomDisplay() {
        const roomCards = document.querySelectorAll('.room-card');
        
        roomCards.forEach((card, index) => {
            const room = this.rooms[index];
            if (!room) return;

            // Add availability badge
            const imageSection = card.querySelector('.room-image-placeholder');
            const existingBadge = card.querySelector('.availability-badge');
            
            if (existingBadge) {
                existingBadge.remove();
            }

            const badge = document.createElement('div');
            badge.className = `availability-badge ${room.status}`;
            badge.innerHTML = room.status === 'booked' 
                ? `<span class="badge-text">BOOKED</span><span class="badge-date">${room.bookedUntil || ''}</span>`
                : `<span class="badge-text">AVAILABLE</span>`;
            
            imageSection.appendChild(badge);

            // Gray out card if booked
            if (room.status === 'booked') {
                card.classList.add('booked');
            } else {
                card.classList.remove('booked');
            }

            // Update price and button state
            const footer = card.querySelector('.room-footer');
            const bookButton = card.querySelector('.btn-secondary');
            
            if (room.status === 'booked') {
                bookButton.textContent = 'Booked';
                bookButton.disabled = true;
                bookButton.style.opacity = '0.6';
                bookButton.style.cursor = 'not-allowed';
            } else {
                bookButton.textContent = 'Book Now';
                bookButton.disabled = false;
                bookButton.style.opacity = '1';
                bookButton.style.cursor = 'pointer';
            }
        });
    }

    // Get room data for specific room
    getRoomData(roomIndex) {
        return this.rooms[roomIndex] || null;
    }
}

// Initialize on page load
let websiteSync;
document.addEventListener('DOMContentLoaded', function() {
    websiteSync = new WebsiteRoomSync();
});

// Listen for storage changes (when admin updates rooms in another tab)
window.addEventListener('storage', function(e) {
    if (e.key === 'khietanRooms') {
        websiteSync.loadRooms();
    }
});
