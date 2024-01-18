<?php

class HelperFunctions
{
    public static function sanitizeInput($input)
    {
        // Simplified input sanitization
        return is_string($input) ? htmlspecialchars(trim($input)) : null;
    }
}
