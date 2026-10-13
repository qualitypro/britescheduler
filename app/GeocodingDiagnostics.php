<?php
declare(strict_types=1);

final class GeocodingDiagnostics
{
    public static function describe(string $status): array
    {
        switch ($status) {
            case 'resolved':
                return [
                    'status' => 'resolved',
                    'label' => 'Address verified',
                    'message' => 'The service address was successfully geocoded.',
                    'action' => null,
                    'retryable' => false
                ];

            case 'pending':
                return [
                    'status' => 'pending',
                    'label' => 'Geocoding pending',
                    'message' => 'Coordinates have not yet been confirmed.',
                    'action' => 'Retry geocoding if the address is complete.',
                    'retryable' => true
                ];

            case 'not_found':
                return [
                    'status' => 'not_found',
                    'label' => 'Address not found',
                    'message' => 'The geocoding provider returned no matching address candidates.',
                    'action' => 'Verify the address details. You may retry the unchanged address 24 hours after the previous attempt.',
                    'retryable' => true
                ];

            case 'rejected':
                return [
                    'status' => 'rejected',
                    'label' => 'Address verification failed',
                    'message' => 'The address input or returned candidates did not satisfy strict address verification.',
                    'action' => 'Check the house number, street name, and ZIP code before saving a corrected address.',
                    'retryable' => false
                ];

            case 'unavailable':
                return [
                    'status' => 'unavailable',
                    'label' => 'Geocoding service unavailable',
                    'message' => 'The geocoding lookup could not be completed.',
                    'action' => 'Retry when the service is available.',
                    'retryable' => true
                ];

            default:
                return [
                    'status' => 'unknown',
                    'label' => 'Unknown geocoding status',
                    'message' => 'The current geocoding state could not be determined.',
                    'action' => 'Refresh the location information.',
                    'retryable' => false
                ];
        }
    }
}
