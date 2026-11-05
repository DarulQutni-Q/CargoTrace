<?php

namespace App\Services;

class IndonesianPortCatalog
{
    /**
     * All 38 Indonesian Provinces.
     */
    public const PROVINCES = [
        'Aceh',
        'Sumatera Utara',
        'Sumatera Barat',
        'Riau',
        'Kepulauan Riau',
        'Jambi',
        'Sumatera Selatan',
        'Bengkulu',
        'Kepulauan Bangka Belitung',
        'Lampung',
        'Banten',
        'DKI Jakarta',
        'Jawa Barat',
        'Jawa Tengah',
        'DI Yogyakarta',
        'Jawa Timur',
        'Bali',
        'Nusa Tenggara Barat',
        'Nusa Tenggara Timur',
        'Kalimantan Barat',
        'Kalimantan Tengah',
        'Kalimantan Selatan',
        'Kalimantan Timur',
        'Kalimantan Utara',
        'Sulawesi Utara',
        'Gorontalo',
        'Sulawesi Tengah',
        'Sulawesi Barat',
        'Sulawesi Selatan',
        'Sulawesi Tenggara',
        'Maluku',
        'Maluku Utara',
        'Papua',
        'Papua Barat',
        'Papua Barat Daya',
        'Papua Tengah',
        'Papua Pegunungan',
        'Papua Selatan',
    ];

    /**
     * Comprehensive Indonesian Kabupaten/Kota & International Port Presets
     * (including Aceh: Krueng Geukuh, Bireuen, Lhokseumawe, Sabang, Malahayati, etc.).
     */
    public static function presets(): array
    {
        return [
            ['code' => 'KRG', 'name' => 'Pelabuhan Krueng Geukuh (Pelindo)', 'city' => 'Krueng Geukuh, Kab. Aceh Utara', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.2417, 'longitude' => 97.0264],
            ['code' => 'LSW', 'name' => 'Pelabuhan Lhokseumawe & KEK Arun', 'city' => 'Kota Lhokseumawe', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.1801, 'longitude' => 97.1507],
            ['code' => 'BIR', 'name' => 'Bireuen Logistics & Cargo Terminal', 'city' => 'Kab. Bireuen', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.2030, 'longitude' => 96.7009],
            ['code' => 'KRJ', 'name' => 'Pelabuhan Kuala Raja Bireuen', 'city' => 'Kuala, Kab. Bireuen', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.2528, 'longitude' => 96.6872],
            ['code' => 'MGD', 'name' => 'Matangglumpangdua Freight Hub', 'city' => 'Peusangan, Kab. Bireuen', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.2056, 'longitude' => 96.7889],
            ['code' => 'MLK', 'name' => 'Bandara Malikussaleh Cargo Terminal', 'city' => 'Lhokseumawe / Aceh Utara', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.2267, 'longitude' => 96.9475],
            ['code' => 'MLH', 'name' => 'Pelabuhan Malahayati Krueng Raya', 'city' => 'Banda Aceh / Kab. Aceh Besar', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.5967, 'longitude' => 95.5122],
            ['code' => 'BTJ', 'name' => 'Bandara Sultan Iskandar Muda Cargo', 'city' => 'Kota Banda Aceh', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.5235, 'longitude' => 95.4204],
            ['code' => 'SBG', 'name' => 'Pelabuhan Bebas Sabang (CT-3 BPKS)', 'city' => 'Kota Sabang (Pulau Weh)', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.8897, 'longitude' => 95.3164],
            ['code' => 'LGS', 'name' => 'Pelabuhan Kuala Langsa', 'city' => 'Kota Langsa', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 4.5256, 'longitude' => 98.0131],
            ['code' => 'MBO', 'name' => 'Pelabuhan Jetty Meulaboh', 'city' => 'Meulaboh, Kab. Aceh Barat', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 4.1362, 'longitude' => 96.1268],
            ['code' => 'BLW', 'name' => 'Pelabuhan Belawan International Container Terminal', 'city' => 'Kota Medan / Belawan', 'province' => 'Sumatera Utara', 'country' => 'Indonesia', 'latitude' => 3.7844, 'longitude' => 98.6836],
            ['code' => 'KNO', 'name' => 'Bandara Kualanamu International Cargo', 'city' => 'Kab. Deli Serdang / Medan', 'province' => 'Sumatera Utara', 'country' => 'Indonesia', 'latitude' => 3.6422, 'longitude' => 98.8853],
            ['code' => 'KTG', 'name' => 'Pelabuhan Internasional Kuala Tanjung', 'city' => 'Kab. Batu Bara', 'province' => 'Sumatera Utara', 'country' => 'Indonesia', 'latitude' => 3.3614, 'longitude' => 99.4494],
            ['code' => 'PDG', 'name' => 'Pelabuhan Teluk Bayur', 'city' => 'Kota Padang', 'province' => 'Sumatera Barat', 'country' => 'Indonesia', 'latitude' => -0.9992, 'longitude' => 100.3703],
            ['code' => 'DUM', 'name' => 'Pelabuhan Dumai (Pelindo)', 'city' => 'Kota Dumai', 'province' => 'Riau', 'country' => 'Indonesia', 'latitude' => 1.6853, 'longitude' => 101.4542],
            ['code' => 'BTH', 'name' => 'Pelabuhan Batu Ampar & Hang Nadim', 'city' => 'Kota Batam', 'province' => 'Kepulauan Riau', 'country' => 'Indonesia', 'latitude' => 1.1619, 'longitude' => 104.0047],
            ['code' => 'PLM', 'name' => 'Pelabuhan Boom Baru Palembang', 'city' => 'Kota Palembang', 'province' => 'Sumatera Selatan', 'country' => 'Indonesia', 'latitude' => -2.9792, 'longitude' => 104.7794],
            ['code' => 'TKG', 'name' => 'Pelabuhan Panjang Lampung', 'city' => 'Kota Bandar Lampung', 'province' => 'Lampung', 'country' => 'Indonesia', 'latitude' => -5.4714, 'longitude' => 105.3183],
            ['code' => 'MRK', 'name' => 'Pelabuhan Merak & Cigading Bulk Port', 'city' => 'Kota Cilegon', 'province' => 'Banten', 'country' => 'Indonesia', 'latitude' => -5.9317, 'longitude' => 105.9958],
            ['code' => 'JKT', 'name' => 'Tanjung Priok Port', 'city' => 'Jakarta Utara', 'province' => 'DKI Jakarta', 'country' => 'Indonesia', 'latitude' => -6.1050, 'longitude' => 106.8800],
            ['code' => 'PTB', 'name' => 'Pelabuhan Internasional Patimban', 'city' => 'Kab. Subang', 'province' => 'Jawa Barat', 'country' => 'Indonesia', 'latitude' => -6.2419, 'longitude' => 107.9061],
            ['code' => 'SRG', 'name' => 'Pelabuhan Tanjung Emas Semarang', 'city' => 'Kota Semarang', 'province' => 'Jawa Tengah', 'country' => 'Indonesia', 'latitude' => -6.9472, 'longitude' => 110.4250],
            ['code' => 'SUB', 'name' => 'Tanjung Perak & Juanda International', 'city' => 'Kota Surabaya', 'province' => 'Jawa Timur', 'country' => 'Indonesia', 'latitude' => -7.3798, 'longitude' => 112.7876],
            ['code' => 'DPS', 'name' => 'Pelabuhan Benoa & Ngurah Rai Cargo', 'city' => 'Kota Denpasar', 'province' => 'Bali', 'country' => 'Indonesia', 'latitude' => -8.7467, 'longitude' => 115.2119],
            ['code' => 'PNK', 'name' => 'Pelabuhan Internasional Kijing & Dwikora', 'city' => 'Kota Pontianak / Mempawah', 'province' => 'Kalimantan Barat', 'country' => 'Indonesia', 'latitude' => -0.0222, 'longitude' => 109.3333],
            ['code' => 'BDJ', 'name' => 'Pelabuhan Trisakti Banjarmasin', 'city' => 'Kota Banjarmasin', 'province' => 'Kalimantan Selatan', 'country' => 'Indonesia', 'latitude' => -3.3289, 'longitude' => 114.5572],
            ['code' => 'BPN', 'name' => 'Pelabuhan Semayang & Kariangau Terminal', 'city' => 'Kota Balikpapan', 'province' => 'Kalimantan Timur', 'country' => 'Indonesia', 'latitude' => -1.2722, 'longitude' => 116.8075],
            ['code' => 'BIT', 'name' => 'Pelabuhan Internasional Bitung', 'city' => 'Kota Bitung', 'province' => 'Sulawesi Utara', 'country' => 'Indonesia', 'latitude' => 1.4414, 'longitude' => 125.1903],
            ['code' => 'UPG', 'name' => 'Makassar New Port (MNP) & Soekarno-Hatta', 'city' => 'Kota Makassar', 'province' => 'Sulawesi Selatan', 'country' => 'Indonesia', 'latitude' => -5.1131, 'longitude' => 119.4067],
            ['code' => 'AMQ', 'name' => 'Pelabuhan Yos Sudarso Ambon', 'city' => 'Kota Ambon', 'province' => 'Maluku', 'country' => 'Indonesia', 'latitude' => -3.6947, 'longitude' => 128.1764],
            ['code' => 'DJJ', 'name' => 'Pelabuhan Jayapura', 'city' => 'Kota Jayapura', 'province' => 'Papua', 'country' => 'Indonesia', 'latitude' => -2.5389, 'longitude' => 140.7083],
            ['code' => 'SOQ', 'name' => 'Pelabuhan Kontainer Sorong', 'city' => 'Kota Sorong', 'province' => 'Papua Barat Daya', 'country' => 'Indonesia', 'latitude' => -0.8806, 'longitude' => 131.2536],
            ['code' => 'SIN', 'name' => 'Port of Singapore · PSA Keppel', 'city' => 'Singapore', 'province' => null, 'country' => 'Singapore', 'latitude' => 1.2644, 'longitude' => 103.8405],
            ['code' => 'PKG', 'name' => 'Port Klang · Westports Hub', 'city' => 'Kuala Lumpur / Selangor', 'province' => null, 'country' => 'Malaysia', 'latitude' => 3.0006, 'longitude' => 101.3928],
            ['code' => 'BKK', 'name' => 'Suvarnabhumi & Laem Chabang Cargo', 'city' => 'Bangkok', 'province' => null, 'country' => 'Thailand', 'latitude' => 13.6900, 'longitude' => 100.7501],
            ['code' => 'CMB', 'name' => 'Port of Colombo Deepwater Hub', 'city' => 'Colombo', 'province' => null, 'country' => 'Sri Lanka', 'latitude' => 6.9531, 'longitude' => 79.8506],
            ['code' => 'DXB', 'name' => 'Dubai Jebel Ali Logistics Hub', 'city' => 'Dubai', 'province' => null, 'country' => 'United Arab Emirates', 'latitude' => 25.0112, 'longitude' => 55.0612],
            ['code' => 'RTM', 'name' => 'Port of Rotterdam · Maasvlakte', 'city' => 'Rotterdam', 'province' => null, 'country' => 'Netherlands', 'latitude' => 51.9496, 'longitude' => 4.1425],
            ['code' => 'HAM', 'name' => 'Hamburg Port & Airport Cargo', 'city' => 'Hamburg', 'province' => null, 'country' => 'Germany', 'latitude' => 53.6304, 'longitude' => 9.9882],
        ];
    }
}
