"""
Room Management CLI - Manage MongoDB rooms from command line
"""
import sys
from mongo_db import MongoRoomDatabase
import json

def print_menu():
    print("\n=== Khiết An Homestay - Room Management ===")
    print("1. List all rooms")
    print("2. Add new room")
    print("3. Update room")
    print("4. Delete room")
    print("5. View statistics")
    print("6. Exit")
    print("=" * 45)

def list_rooms(db):
    """Display all rooms"""
    rooms = db.get_all_rooms()
    if not rooms:
        print("No rooms found.")
        return
    
    print("\n=== Rooms List ===")
    for room in rooms:
        print(f"\nID: {room.get('room_id', 'N/A')}")
        print(f"Name: {room['name']}")
        print(f"Price: ${room['price']}/night")
        print(f"Capacity: {room['capacity']} guests")
        print(f"Status: {room['status']}")
        if room.get('booked_until'):
            print(f"Booked Until: {room['booked_until']}")
        print("-" * 40)

def add_room(db):
    """Add a new room"""
    print("\n=== Add New Room ===")
    try:
        name = input("Room Name: ").strip()
        price = float(input("Price per Night (USD): "))
        capacity = int(input("Guest Capacity: "))
        description = input("Description: ").strip()
        amenities_str = input("Amenities (comma-separated): ").strip()
        amenities = [a.strip() for a in amenities_str.split(',')]
        
        room_data = {
            'name': name,
            'price': price,
            'capacity': capacity,
            'description': description,
            'amenities': amenities,
            'status': 'available'
        }
        
        room = db.add_room(room_data)
        print(f"\n✓ Room added successfully! (ID: {room['room_id']})")
    except ValueError as e:
        print(f"✗ Invalid input: {e}")

def update_room(db):
    """Update an existing room"""
    print("\n=== Update Room ===")
    try:
        room_id = int(input("Room ID to update: "))
        room = db.get_room_by_id(room_id)
        
        if not room:
            print(f"✗ Room with ID {room_id} not found.")
            return
        
        print(f"\nCurrent: {room['name']} - ${room['price']}/night ({room['status']})")
        print("(Leave empty to keep current value)")
        
        update_data = {}
        
        name = input("New Name: ").strip()
        if name:
            update_data['name'] = name
        
        price_str = input("New Price: ").strip()
        if price_str:
            update_data['price'] = float(price_str)
        
        status = input("New Status (available/booked/maintenance): ").strip()
        if status:
            update_data['status'] = status
        
        if status == 'booked':
            booked_until = input("Booked Until (YYYY-MM-DD): ").strip()
            if booked_until:
                update_data['booked_until'] = booked_until
        
        db.update_room(room_id, update_data)
        print(f"✓ Room updated successfully!")
    except ValueError as e:
        print(f"✗ Invalid input: {e}")

def delete_room(db):
    """Delete a room"""
    print("\n=== Delete Room ===")
    try:
        room_id = int(input("Room ID to delete: "))
        room = db.get_room_by_id(room_id)
        
        if not room:
            print(f"✗ Room with ID {room_id} not found.")
            return
        
        confirm = input(f"Are you sure you want to delete '{room['name']}'? (y/n): ")
        if confirm.lower() == 'y':
            if db.delete_room(room_id):
                print("✓ Room deleted successfully!")
            else:
                print("✗ Failed to delete room.")
    except ValueError as e:
        print(f"✗ Invalid input: {e}")

def view_stats(db):
    """Display room statistics"""
    stats = db.get_stats()
    print("\n=== Room Statistics ===")
    print(f"Total Rooms: {stats['total']}")
    print(f"Available: {stats['available']}")
    print(f"Booked: {stats['booked']}")
    print(f"Maintenance: {stats['maintenance']}")

def main():
    """Main program loop"""
    try:
        db = MongoRoomDatabase()
        
        while True:
            print_menu()
            choice = input("Select option (1-6): ").strip()
            
            if choice == '1':
                list_rooms(db)
            elif choice == '2':
                add_room(db)
            elif choice == '3':
                update_room(db)
            elif choice == '4':
                delete_room(db)
            elif choice == '5':
                view_stats(db)
            elif choice == '6':
                print("Goodbye!")
                db.close()
                break
            else:
                print("✗ Invalid option. Please try again.")
    
    except KeyboardInterrupt:
        print("\n\nProgram interrupted.")
        db.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == '__main__':
    main()
