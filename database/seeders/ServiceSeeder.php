<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
      $services = [
            ['service_name' => 'Mouth Examination', 'description' => 'General oral health check.'],
            ['service_name' => 'Oral Prophylaxis (Cleaning)', 'description' => 'Teeth cleaning procedure.'],
            ['service_name' => 'Tooth Restoration (Filling)', 'description' => 'Dental filling procedure.'],
            ['service_name' => 'Tooth Extraction', 'description' => 'Removal of damaged or decayed tooth.'],
            ['service_name' => 'Dental Veneers', 'description' => 'Cosmetic restoration for teeth.'],
            ['service_name' => 'Teeth Whitening', 'description' => 'Cosmetic whitening treatment.'],
            ['service_name' => 'Crowns and Bridges', 'description' => 'Replacement for damaged teeth.'],
            ['service_name' => 'Partial Denture', 'description' => 'Removable denture for some missing teeth.'],
            ['service_name' => 'Complete Denture', 'description' => 'Full replacement for missing teeth.'],
            ['service_name' => 'Root Canal Treatment', 'description' => 'Treatment for infected tooth pulp.'],
            ['service_name' => 'Wisdom Tooth Removal', 'description' => 'Extraction of wisdom teeth.'],
            ['service_name' => 'Digital Panoramic X-ray', 'description' => 'Full mouth x-ray imaging.'],
            ['service_name' => 'Digital Cephalometric X-ray', 'description' => 'Orthodontic imaging.'],
            ['service_name' => 'TMJ X-ray', 'description' => 'Temporomandibular joint imaging.'],
            ['service_name' => 'Periapical X-ray', 'description' => 'Detailed x-ray of specific teeth.'],
            ['service_name' => 'Metal Braces', 'description' => 'Traditional orthodontic braces.'],
            ['service_name' => 'Ceramic Braces', 'description' => 'Tooth-colored braces.'],
            ['service_name' => 'Self-Ligating Braces', 'description' => 'Braces with sliding mechanism.'],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
