#!/bin/bash

# Set the file name length limit (default is 260)
path_limit=200

# Function to recursively list files and check their lengths
check_file_lengths() {
  local dir="$1"
  local limit="$2"
  local count=0

  # Iterate over all files and directories in the given directory
  for entry in "$dir"/*; do
    if [[ -f "$entry" ]]; then
      # Get the file name and its length
      file_name=$(basename "$entry")
      length=${#file_name}

      # Check if the length exceeds the limit
      if (( length > limit )); then
        echo "$entry"
        count=$((count + 1))
      fi
    elif [[ -d "$entry" ]]; then
      # Recursively check files in subdirectories
      check_file_lengths "$entry" "$limit"
    fi
  done

  return $count
}

# Call the function starting from the current directory
count=$(check_file_lengths "." "$path_limit")

if (( count == 0 )); then
  echo "No files with lengths exceeding the limit."
  exit 0
else
  echo "Total files with lengths exceeding the limit: $count"
  exit 1
fi
