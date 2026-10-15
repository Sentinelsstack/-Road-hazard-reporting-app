<?php

namespace Database\Seeders;

use App\Models\Hazard;
use App\Models\User;
use Illuminate\Database\Seeder;

class HazardSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        $hazards = [
            [
                'type' => 'pothole',
                'description' => 'Large pothole on the main road near the intersection, approximately 30cm wide and 15cm deep. Causing vehicles to swerve dangerously.',
                'lat' => 14.5995,
                'lng' => 120.9842,
                'status' => 'confirmed',
                'user_id' => $user->id,
            ],
            [
                'type' => 'fallen_tree',
                'description' => 'A large acacia tree has fallen across the road after last night\'s storm. Road is completely blocked.',
                'lat' => 14.5547,
                'lng' => 120.9932,
                'status' => 'pending',
                'user_id' => $user->id,
            ],
            [
                'type' => 'flooding',
                'description' => 'Street flooding reaching knee-deep levels after heavy rainfall. Drainage system is overwhelmed.',
                'lat' => 14.5672,
                'lng' => 121.0194,
                'status' => 'confirmed',
                'user_id' => $user->id,
            ],
            [
                'type' => 'broken_traffic_light',
                'description' => 'Traffic light at the crossing is not functioning. Need traffic enforcer assistance during peak hours.',
                'lat' => 14.5820,
                'lng' => 121.0083,
                'status' => 'pending',
                'user_id' => $user->id,
            ],
            [
                'type' => 'road_damage',
                'description' => 'Road surface has collapsed near the drainage culvert. Large crack spanning across the lane.',
                'lat' => 14.6102,
                'lng' => 120.9941,
                'status' => 'resolved',
                'user_id' => $user->id,
            ],
            [
                'type' => 'landslide',
                'description' => 'Minor landslide on the mountain road. Debris covering one lane. Proceed with caution.',
                'lat' => 14.5413,
                'lng' => 120.9765,
                'status' => 'pending',
                'user_id' => $user->id,
            ],
            [
                'type' => 'pothole',
                'description' => 'Multiple small potholes along a 100-meter stretch of the service road. Hazardous for motorcycles.',
                'lat' => 14.5938,
                'lng' => 121.0341,
                'status' => 'pending',
                'user_id' => $user->id,
            ],
            [
                'type' => 'manhole_cover_missing',
                'description' => 'Manhole cover missing on the pedestrian crossing. Exposed hole approximately 1 meter deep.',
                'lat' => 14.5751,
                'lng' => 120.9878,
                'status' => 'confirmed',
                'user_id' => $user->id,
            ],
            [
                'type' => 'flooding',
                'description' => 'Flash flood in the underpass. Water level too high for vehicles to pass safely.',
                'lat' => 14.5489,
                'lng' => 121.0287,
                'status' => 'confirmed',
                'user_id' => $user->id,
            ],
            [
                'type' => 'broken_traffic_light',
                'description' => 'Pedestrian crossing light not working near the school zone. Dangerous for children crossing.',
                'lat' => 14.6034,
                'lng' => 120.9652,
                'status' => 'resolved',
                'user_id' => $user->id,
            ],
            [
                'type' => 'road_damage',
                'description' => 'Road shoulder eroded by flooding. Edge of asphalt is crumbling into the drainage ditch.',
                'lat' => 14.5387,
                'lng' => 120.9995,
                'status' => 'pending',
                'user_id' => $user->id,
            ],
            [
                'type' => 'pothole',
                'description' => 'Deep pothole right on the center of the roundabout. Difficult to avoid during heavy traffic.',
                'lat' => 14.5883,
                'lng' => 121.0176,
                'status' => 'pending',
                'user_id' => $user->id,
            ],
            [
                'type' => 'road_accident',
                'description' => 'Multi-vehicle collision at the intersection. Two cars and a motorcycle involved. Traffic heavily affected. Emergency services on the way.',
                'lat' => 14.5721,
                'lng' => 121.0113,
                'status' => 'confirmed',
                'user_id' => $user->id,
            ],
            [
                'type' => 'road_accident',
                'description' => 'Single vehicle hit a utility pole on the highway shoulder. Driver reported minor injuries. Lane blocked.',
                'lat' => 14.5967,
                'lng' => 120.9774,
                'status' => 'pending',
                'user_id' => $user->id,
            ],
        ];

        foreach ($hazards as $hazard) {
            Hazard::create($hazard);
        }

        $this->command->info('Seeded ' . count($hazards) . ' demo hazards.');
    }
}
