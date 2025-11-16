<?php
/**
 * Date Helper
 * 
 * Provides utility functions for formatting dates
 */
/**
 * Format a date based on the "Has release date type" property
 * 
 * @param string $rawDate The raw date from SMW (e.g., "1/1986", "10/2003", "12/31/2024")
 * @param int $timestamp The timestamp of the date
 * @param string $dateType The date type: "Year", "Month", "Quarter", "Full", or "None"
 * @return string The formatted date string
 */
function formatReleaseDate($rawDate, $timestamp, $dateType) {
    if (!$timestamp || $dateType === 'None') {
        return $rawDate;
    }
    
    switch ($dateType) {
        case 'Year':
            return date('Y', $timestamp);
        case 'Month':
            return date('F Y', $timestamp);
        case 'Quarter':
            $quarter = ceil(date('n', $timestamp) / 3);
            return 'Q' . $quarter . ' ' . date('Y', $timestamp);
        case 'Full':
        default:
            return date('F j, Y', $timestamp);
    }
}

