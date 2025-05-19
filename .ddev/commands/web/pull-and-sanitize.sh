#!/bin/bash

## Description: Pull a database snapshot and sanitize it
## Usage: pull-and-sanitize [snapshot_name]
## Example: "ddev pull-and-sanitize developer" or "ddev pull-and-sanitize" (uses default snapshot)

# Set default snapshot name if not provided
SNAPSHOT_NAME=${1:-"default"}

# Ensure we're in the project root
cd /var/www/html

echo "Pulling snapshot: $SNAPSHOT_NAME"

# Use testor to get the snapshot
./testor snapshot:get --name="$SNAPSHOT_NAME" --element=database

# Check if snapshot retrieval was successful
if [ $? -ne 0 ]; then
    echo "Failed to retrieve snapshot: $SNAPSHOT_NAME"
    exit 1
fi

# Get the path to the downloaded snapshot file
# This assumes the snapshot:get command outputs the path or we can determine it
# You may need to adjust this logic based on your actual implementation
SNAPSHOT_FILE=$(find . -name "*$SNAPSHOT_NAME*.sql*" -type f -print -quit)

if [ -z "$SNAPSHOT_FILE" ]; then
    echo "Could not find downloaded snapshot file"
    exit 1
fi

echo "Found snapshot file: $SNAPSHOT_FILE"

# Import the snapshot into the database
echo "Importing snapshot into database..."
if [[ "$SNAPSHOT_FILE" == *.gz ]]; then
    # For gzipped SQL files
    gunzip -c "$SNAPSHOT_FILE" | drush sql:cli
else
    # For plain SQL files
    drush sql:cli < "$SNAPSHOT_FILE"
fi

# Check if import was successful
if [ $? -ne 0 ]; then
    echo "Failed to import snapshot into database"
    exit 1
fi

# Run sanitization
echo "Sanitizing database..."
drush sql:sanitize --yes

# Check if sanitization was successful
if [ $? -ne 0 ]; then
    echo "Database sanitization failed"
    exit 1
fi

echo "Database snapshot pulled and sanitized successfully!"
exit 0
