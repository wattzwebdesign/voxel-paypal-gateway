#!/bin/bash

# WordPress Plugin Packaging Script
# Packages voxel-paypal-gateway plugin into a versioned ZIP file
# Excludes: macOS files, git files, Claude files, and all hidden files

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo -e "${YELLOW}=== Voxel PayPal Gateway Packaging Script ===${NC}\n"

# Extract version from main plugin file
VERSION=$(grep -m 1 "Version:" voxel-paypal-gateway.php | awk '{print $3}' | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from voxel-paypal-gateway.php${NC}"
    exit 1
fi

echo -e "${GREEN}Found version: ${VERSION}${NC}\n"

# Define output filenames
PLUGIN_NAME="voxel-paypal-gateway"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
TEMP_DIR="temp_${PLUGIN_NAME}"

# Remove old temp directory if it exists
if [ -d "$TEMP_DIR" ]; then
    echo "Cleaning up old temporary files..."
    rm -rf "$TEMP_DIR"
fi

# Remove old zip if it exists
if [ -f "$ZIP_NAME" ]; then
    echo "Removing existing ${ZIP_NAME}..."
    rm "$ZIP_NAME"
fi

echo -e "\n${YELLOW}Creating temporary build directory...${NC}"
mkdir -p "$TEMP_DIR/$PLUGIN_NAME"

# Copy files using rsync with exclusions
echo -e "${YELLOW}Copying plugin files...${NC}"
rsync -av \
    --exclude='.git*' \
    --exclude='.claude' \
    --exclude='.DS_Store' \
    --exclude='__MACOSX' \
    --exclude='*.sh' \
    --exclude='temp_*' \
    --exclude='.*' \
    --exclude="${PLUGIN_NAME}-*.zip" \
    --exclude='node_modules' \
    --exclude='.npm' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='composer.lock' \
    --exclude='package-lock.json' \
    --exclude='yarn.lock' \
    . "$TEMP_DIR/$PLUGIN_NAME/"

echo -e "\n${YELLOW}Creating ZIP archive...${NC}"
cd "$TEMP_DIR"
zip -r "../$ZIP_NAME" "$PLUGIN_NAME" -q

cd ..
echo -e "${GREEN}✓ Created: ${ZIP_NAME}${NC}"

# Get file size
SIZE=$(du -h "$ZIP_NAME" | cut -f1)
echo -e "${GREEN}✓ Size: ${SIZE}${NC}"

# Clean up temporary directory
echo -e "\n${YELLOW}Cleaning up temporary files...${NC}"
rm -rf "$TEMP_DIR"

echo -e "\n${GREEN}=== Packaging complete! ===${NC}"
echo -e "${GREEN}Package: ${ZIP_NAME}${NC}"
echo -e "\nYou can now upload this file to WordPress or distribute it.\n"
