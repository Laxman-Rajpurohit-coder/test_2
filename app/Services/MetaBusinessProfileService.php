<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaBusinessProfileService
{
    /**
     * Fetch WhatsApp Business Profile details from Meta Graph API.
     *
     * @param string $accessToken Meta / WhatsApp System User Access Token
     * @param string $phoneNumberId WhatsApp Phone Number ID
     * @return array
     */
    public function getProfile(string $accessToken, string $phoneNumberId): array
    {
        try {
            $endpoint = "https://graph.facebook.com/v21.0/{$phoneNumberId}/whatsapp_business_profile";

            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->get($endpoint, [
                    'fields' => 'about,address,description,email,profile_picture_url,websites,vertical',
                ]);

            $data = $response->json() ?? [];

            if ($response->successful()) {
                $profile = $data['data'][0] ?? $data;
                $vertical = strtoupper($profile['vertical'] ?? 'OTHER');
                if ($vertical === 'UNDEFINED' || empty($vertical)) {
                    $vertical = 'OTHER';
                }

                return [
                    'success' => true,
                    'data'    => [
                        'about'               => $profile['about'] ?? '',
                        'address'             => $profile['address'] ?? '',
                        'description'         => $profile['description'] ?? '',
                        'email'               => $profile['email'] ?? '',
                        'profile_picture_url' => $profile['profile_picture_url'] ?? '',
                        'websites'            => $profile['websites'] ?? [],
                        'vertical'            => $vertical,
                    ],
                    'error'   => null,
                ];
            }

            $errorMessage = $data['error']['message'] ?? $response->body() ?: 'Failed to fetch WhatsApp Business Profile.';
            Log::warning('MetaBusinessProfileService: Fetch profile failed', [
                'status' => $response->status(),
                'error'  => $errorMessage,
                'phone'  => $phoneNumberId,
            ]);

            return [
                'success' => false,
                'data'    => null,
                'error'   => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaBusinessProfileService: Exception fetching profile', [
                'message' => $e->getMessage(),
                'phone'   => $phoneNumberId,
            ]);

            return [
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Update WhatsApp Business Profile details on Meta Graph API.
     *
     * @param string $accessToken Meta Access Token
     * @param string $phoneNumberId WhatsApp Phone Number ID
     * @param array $fields Form fields (about, address, description, email, websites, vertical, profile_picture_handle)
     * @return array
     */
    public function updateProfile(string $accessToken, string $phoneNumberId, array $fields): array
    {
        try {
            $endpoint = "https://graph.facebook.com/v21.0/{$phoneNumberId}/whatsapp_business_profile";

            $payload = [
                'messaging_product' => 'whatsapp',
            ];

            if (isset($fields['about']) && trim($fields['about']) !== '') {
                $payload['about'] = trim($fields['about']);
            }
            if (isset($fields['address']) && trim($fields['address']) !== '') {
                $payload['address'] = trim($fields['address']);
            }
            if (isset($fields['description']) && trim($fields['description']) !== '') {
                $payload['description'] = trim($fields['description']);
            }
            if (isset($fields['email']) && trim($fields['email']) !== '') {
                $payload['email'] = trim($fields['email']);
            }
            
            $allowedVerticals = ['OTHER', 'AUTO', 'BEAUTY', 'APPAREL', 'EDU', 'ENTERTAIN', 'EVENT_PLAN', 'FINANCE', 'GROCERY', 'GOVT', 'HOTEL', 'HEALTH', 'NONPROFIT', 'PROF_SERVICES', 'RETAIL', 'TRAVEL', 'RESTAURANT', 'ALCOHOL', 'ONLINE_GAMBLING', 'PHYSICAL_GAMBLING', 'OTC_DRUGS', 'MATRIMONY_SERVICE'];
            $reqVertical = strtoupper($fields['vertical'] ?? 'OTHER');
            $payload['vertical'] = in_array($reqVertical, $allowedVerticals) ? $reqVertical : 'OTHER';

            if (!empty($fields['websites'])) {
                $filteredWebsites = is_array($fields['websites']) 
                    ? array_values(array_filter(array_map('trim', $fields['websites']))) 
                    : [trim($fields['websites'])];
                if (!empty($filteredWebsites)) {
                    $payload['websites'] = $filteredWebsites;
                }
            }

            if (!empty($fields['profile_picture_handle'])) {
                $payload['profile_picture_handle'] = $fields['profile_picture_handle'];
            }

            $response = Http::timeout(15)
                ->withToken($accessToken)
                ->post($endpoint, $payload);

            $data = $response->json() ?? [];

            if ($response->successful() && ($data['success'] ?? true)) {
                return [
                    'success' => true,
                    'error'   => null,
                ];
            }

            $errorMessage = $data['error']['message'] ?? $response->body() ?: 'Failed to update WhatsApp Business Profile.';
            Log::warning('MetaBusinessProfileService: Update profile failed', [
                'status'  => $response->status(),
                'error'   => $errorMessage,
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error'   => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaBusinessProfileService: Exception updating profile', [
                'message' => $e->getMessage(),
                'phone'   => $phoneNumberId,
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload an image to Meta Resumable Upload API to obtain a valid profile_picture_handle (h).
     *
     * Automatically center-crops and standardizes the image to a 640x640 square JPEG,
     * fulfilling Meta's strict WhatsApp Business Profile 1:1 image specification.
     *
     * @param string $accessToken
     * @param string $phoneNumberId
     * @param \Illuminate\Http\UploadedFile $file
     * @return array{success: bool, handle: ?string, error: ?string}
     */
    public function uploadMedia(string $accessToken, string $phoneNumberId, $file): array
    {
        try {
            $realPath = $file->getRealPath();
            
            // Auto-normalize image: convert and center-crop to a standard 640x640 square JPEG for Meta
            $fileContent = null;
            if (extension_loaded('gd')) {
                $imageInfo = @getimagesize($realPath);
                if ($imageInfo) {
                    $mime = $imageInfo['mime'] ?? '';
                    $src = null;
                    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                        $src = @imagecreatefromjpeg($realPath);
                    } elseif ($mime === 'image/png') {
                        $src = @imagecreatefrompng($realPath);
                    } elseif ($mime === 'image/webp') {
                        $src = @imagecreatefromwebp($realPath);
                    }

                    if ($src) {
                        $origW = imagesx($src);
                        $origH = imagesy($src);
                        $targetSize = 640;

                        // Calculate center-crop coordinates
                        $minDim = min($origW, $origH);
                        $srcX = ($origW - $minDim) / 2;
                        $srcY = ($origH - $minDim) / 2;

                        $dest = imagecreatetruecolor($targetSize, $targetSize);
                        
                        // Fill white background for transparent PNGs
                        $white = imagecolorallocate($dest, 255, 255, 255);
                        imagefill($dest, 0, 0, $white);

                        imagecopyresampled($dest, $src, 0, 0, (int)$srcX, (int)$srcY, $targetSize, $targetSize, (int)$minDim, (int)$minDim);

                        ob_start();
                        imagejpeg($dest, null, 92);
                        $fileContent = ob_get_clean();

                        imagedestroy($src);
                        imagedestroy($dest);
                    }
                }
            }

            if (!$fileContent) {
                $fileContent = file_get_contents($realPath);
            }

            $fileLength = strlen($fileContent);
            $mimeType = 'image/jpeg';

            // Step 1: Create Resumable Upload Session on Meta Graph API
            $sessionEndpoint = "https://graph.facebook.com/v21.0/app/uploads";
            $sessionResponse = Http::timeout(15)
                ->withToken($accessToken)
                ->post($sessionEndpoint, [
                    'file_length' => $fileLength,
                    'file_type'   => $mimeType,
                ]);

            $sessionData = $sessionResponse->json() ?? [];
            $uploadSessionId = $sessionData['id'] ?? null;

            if (!$uploadSessionId) {
                $err = $sessionData['error']['message'] ?? $sessionResponse->body() ?: 'Failed to initiate Meta upload session.';
                Log::warning('MetaBusinessProfileService: Upload session creation failed', [
                    'error' => $err,
                    'phone' => $phoneNumberId,
                ]);
                return [
                    'success' => false,
                    'handle'  => null,
                    'error'   => $err,
                ];
            }

            // Step 2: Upload raw file binary to the session to get the profile_picture_handle (h)
            $uploadEndpoint = "https://graph.facebook.com/v21.0/{$uploadSessionId}";
            $uploadResponse = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "OAuth {$accessToken}",
                    'file_offset'   => 0,
                    'Content-Type'  => 'application/octet-stream',
                ])
                ->withBody($fileContent, 'application/octet-stream')
                ->post($uploadEndpoint);

            $uploadData = $uploadResponse->json() ?? [];
            $handle = $uploadData['h'] ?? null;

            if ($uploadResponse->successful() && !empty($handle)) {
                Log::info('MetaBusinessProfileService: Uploaded profile picture handle successfully', [
                    'handle' => $handle,
                    'phone'  => $phoneNumberId,
                ]);
                return [
                    'success' => true,
                    'handle'  => $handle,
                    'error'   => null,
                ];
            }

            $errorMessage = $uploadData['error']['message'] ?? $uploadResponse->body() ?: 'Failed to upload image data to Meta upload session.';
            Log::warning('MetaBusinessProfileService: Upload data chunk failed', [
                'error' => $errorMessage,
                'phone' => $phoneNumberId,
            ]);

            return [
                'success' => false,
                'handle'  => null,
                'error'   => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaBusinessProfileService: Exception in uploadMedia', [
                'message' => $e->getMessage(),
                'phone'   => $phoneNumberId,
            ]);

            return [
                'success' => false,
                'handle'  => null,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
