#!/bin/bash
set -e

echo "Starting deployment build..."

# Clean previous installations
rm -rf node_modules package-lock.json

# Install dependencies with force flag to handle optional dependencies
npm install --force --include=optional

# Build the application
npm run build

echo "Build completed successfully!"
