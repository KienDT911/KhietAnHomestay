import os
from dotenv import load_dotenv
from pymongo import MongoClient
from pymongo.errors import ConnectionFailure
from datetime import datetime

# Load environment variables
load_dotenv()

class MongoRoomDatabase:
    def __init__(self, connection_string=None, db_name='khiet_an_homestay', collection_name='rooms'):
        """
        Initialize MongoDB connection
        
        Args:
            connection_string: MongoDB connection URI (default from .env)
            db_name: Database name (default: 'khiet_an_homestay')
            collection_name: Collection name (default: 'rooms')
        """
        try:
            if not connection_string:
                connection_string = os.getenv('MONGODB_URI', 'mongodb://localhost:27017/')
            
            self.client = MongoClient(connection_string)
            self.db = self.client[db_name]
            self.collection = self.db[collection_name]
            
            # Test connection
            self.client.admin.command('ping')
            print(f"✓ Connected to MongoDB: {db_name}/{collection_name}")
            
            # Create index for room IDs
            self.collection.create_index('room_id', unique=True, sparse=True)
            
        except ConnectionFailure as e:
            print(f"✗ Failed to connect to MongoDB: {e}")
            raise
    
    def get_all_rooms(self):
        """Get all rooms"""
        rooms = list(self.collection.find({}, {'_id': 0}))
        return rooms
    
    def get_room_by_id(self, room_id):
        """Get a specific room by ID"""
        room = self.collection.find_one({'room_id': room_id}, {'_id': 0})
        return room
    
    def add_room(self, room_data):
        """Add a new room"""
        room = {
            'name': room_data.get('name'),
            'price': float(room_data.get('price', 0)),
            'capacity': int(room_data.get('capacity', 1)),
            'description': room_data.get('description', ''),
            'amenities': room_data.get('amenities', []),
            'status': room_data.get('status', 'available'),
            'image_url': room_data.get('image_url', ''),
            'bookings': room_data.get('bookings', []),  # Array of booking objects
            'created_at': datetime.utcnow(),
            'updated_at': datetime.utcnow()
        }
        
        # Auto-generate room_id if not provided
        if 'room_id' not in room_data:
            last_room = self.collection.find_one(sort=[('room_id', -1)])
            room['room_id'] = (last_room['room_id'] if last_room else 0) + 1
        else:
            room['room_id'] = room_data['room_id']
        
        result = self.collection.insert_one(room)
        room['_id'] = result.inserted_id
        return room
    
    def update_room(self, room_id, room_data):
        """Update an existing room"""
        update_data = {
            'updated_at': datetime.utcnow()
        }
        
        # Only update provided fields
        for key in ['name', 'price', 'capacity', 'description', 'amenities', 'status', 'image_url', 'bookings']:
            if key in room_data:
                update_data[key] = room_data[key]
        
        result = self.collection.update_one(
            {'room_id': room_id},
            {'$set': update_data}
        )
        
        if result.matched_count > 0:
            return self.get_room_by_id(room_id)
        return None
    
    def delete_room(self, room_id):
        """Delete a room"""
        result = self.collection.delete_one({'room_id': room_id})
        return result.deleted_count > 0
    
    def get_stats(self):
        """Get room statistics"""
        total = self.collection.count_documents({})
        available = self.collection.count_documents({'status': 'available'})
        booked = self.collection.count_documents({'status': 'booked'})
        
        return {
            'total': total,
            'available': available,
            'booked': booked,
            'maintenance': total - available - booked
        }
    
    def add_booking(self, room_id, booking_data):
        """Add a booking to a room"""
        booking = {
            'booking_id': booking_data.get('booking_id'),
            'guest_name': booking_data.get('guest_name'),
            'guest_email': booking_data.get('guest_email'),
            'guest_phone': booking_data.get('guest_phone'),
            'check_in': booking_data.get('check_in'),  # ISO datetime string
            'check_out': booking_data.get('check_out'),  # ISO datetime string
            'number_of_guests': booking_data.get('number_of_guests', 1),
            'total_price': float(booking_data.get('total_price', 0)),
            'status': booking_data.get('status', 'confirmed'),  # confirmed, cancelled, completed
            'notes': booking_data.get('notes', ''),
            'created_at': datetime.utcnow()
        }
        
        result = self.collection.update_one(
            {'room_id': room_id},
            {'$push': {'bookings': booking}, '$set': {'updated_at': datetime.utcnow()}}
        )
        
        return result.modified_count > 0
    
    def update_booking(self, room_id, booking_id, booking_data):
        """Update a specific booking"""
        update_data = {}
        for key in ['guest_name', 'guest_email', 'guest_phone', 'check_in', 'check_out', 'number_of_guests', 'total_price', 'status', 'notes']:
            if key in booking_data:
                update_data[f'bookings.$.{key}'] = booking_data[key]
        
        update_data['updated_at'] = datetime.utcnow()
        
        result = self.collection.update_one(
            {'room_id': room_id, 'bookings.booking_id': booking_id},
            {'$set': update_data}
        )
        
        return result.modified_count > 0
    
    def delete_booking(self, room_id, booking_id):
        """Delete a specific booking"""
        result = self.collection.update_one(
            {'room_id': room_id},
            {'$pull': {'bookings': {'booking_id': booking_id}}, '$set': {'updated_at': datetime.utcnow()}}
        )
        
        return result.modified_count > 0
    
    def get_room_bookings(self, room_id):
        """Get all bookings for a specific room"""
        room = self.collection.find_one({'room_id': room_id}, {'bookings': 1})
        return room['bookings'] if room else []
    
    def sync_rooms(self, rooms_data):
        """Sync multiple rooms (useful for importing data)"""
        count = 0
        for room in rooms_data:
            if 'room_id' in room:
                if self.get_room_by_id(room['room_id']):
                    self.update_room(room['room_id'], room)
                else:
                    self.add_room(room)
            else:
                self.add_room(room)
            count += 1
        
        return {'synced': count, 'total': len(rooms_data)}
    
    def close(self):
        """Close MongoDB connection"""
        self.client.close()
        print("✓ MongoDB connection closed")


# Example usage
if __name__ == '__main__':
    try:
        db = MongoRoomDatabase()
        
        # Get all rooms
        print("\n=== All Rooms ===")
        rooms = db.get_all_rooms()
        for room in rooms:
            print(f"• {room['name']} - ${room['price']}/night ({room['status']})")
        
        # Get statistics
        print("\n=== Statistics ===")
        stats = db.get_stats()
        print(f"Total: {stats['total']}, Available: {stats['available']}, Booked: {stats['booked']}")
        
        db.close()
        
    except Exception as e:
        print(f"Error: {e}")
