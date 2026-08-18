<?php

namespace Database\Seeders;

use App\Models\IndonesiaRegion;
use Illuminate\Database\Seeder;

class IndonesiaRegionsSeeder extends Seeder
{
    public function run(): void
    {
        $dataWilayah = [
            [
                'provinsi' => 'Sumatera Barat',
                'kotaList' => [
                    [
                        'nama' => 'Kota Padang',
                        'kecamatanList' => [
                            ['nama' => 'Bungus Teluk Kabung', 'kelurahanList' => ['Bungus Barat', 'Bungus Selatan', 'Bungus Timur', 'Teluk Kabung Selatan', 'Teluk Kabung Tengah', 'Teluk Kabung Utara']],
                            ['nama' => 'Koto Tangah', 'kelurahanList' => ['Air Pacah', 'Balai Gadang', 'Batang Kabung Ganting', 'Dadok Tunggul Hitam', 'Koto Panjang Ikur Koto', 'Koto Pulai', 'Lubuk Buaya', 'Lubuk Minturun', 'Parupuk Tabing', 'Pasie Nan Tigo']],
                            ['nama' => 'Kuranji', 'kelurahanList' => ['Ampang', 'Anduring', 'Kalumbuk', 'Korong Gadang', 'Kuranji', 'Lubuk Lintah', 'Pasar Ambacang', 'Sungai Sapih']],
                            ['nama' => 'Lubuk Begalung', 'kelurahanList' => ['Batu Gadang', 'Cengkeh Nan XX', 'Gates Nan XX', 'Kampung Baru Nan XX', 'Koto Baru Nan XX', 'Lubuk Begalung Nan XX', 'Pagambiran Ampalu Nan XX', 'Pampangan Nan XX', 'Tanjung Aur Nan XX']],
                            ['nama' => 'Lubuk Kilangan', 'kelurahanList' => ['Bandar Buat', 'Batu Gadang', 'Indarung', 'Koto Lalang', 'Padang Besi', 'Tarantang']],
                            ['nama' => 'Nanggalo', 'kelurahanList' => ['Gurun Laweh Nan XX', 'Kampung Lapai', 'Kampung Olo', 'Kurao Pagang', 'Surau Gadang', 'Tabing Banda Gadang']],
                            ['nama' => 'Padang Barat', 'kelurahanList' => ['Belakang Tangsi', 'Berok Nipah', 'Kampung Jao', 'Kampung Pondok', 'Purus', 'Rimbo Kaluang', 'Ujung Gurun']],
                            ['nama' => 'Padang Selatan', 'kelurahanList' => ['Air Manis', 'Alang Laweh', 'Batang Arau', 'Bukit Gado-Gado', 'Mata Air', 'Rawang', 'Seberang Padang', 'Seberang Palinggam', 'Teluk Bayur']],
                            ['nama' => 'Padang Timur', 'kelurahanList' => ['Andalas', 'Belakang Pondok', 'Ganting Parak Gadang', 'Jati', 'Jati Baru', 'Kubu Marapalam', 'Parak Gadang Timur', 'Sawahan', 'Sawahan Timur']],
                            ['nama' => 'Padang Utara', 'kelurahanList' => ['Air Tawar Barat', 'Air Tawar Timur', 'Alai Parak Kopi', 'Gunung Pangilun', 'Lolong Belanti', 'Ulak Karang Selatan', 'Ulak Karang Utara']],
                            ['nama' => 'Pauh', 'kelurahanList' => ['Binuang Kampung Dalam', 'Cupak Tangah', 'Kapalo Koto', 'Koto Luar', 'Lambung Bukit', 'Limau Manis', 'Limau Manis Selatan', 'Pisang']],
                        ],
                    ],
                    [
                        'nama' => 'Kota Bukittinggi',
                        'kecamatanList' => [
                            ['nama' => 'Guguk Panjang', 'kelurahanList' => ['Bukit Cangang Kayu Ramang', 'Pakan Kurai', 'Tarok Dipo', 'Aur Tajungkang Tengah Sawah', 'Benteng Pasar Atas']],
                            ['nama' => 'Mandiangin Koto Selayan', 'kelurahanList' => ['Campago Guguak Bulek', 'Campago Ipuh', 'Garegeh', 'Koto Selayan', 'Kubu Gulai Bancah', 'Pulai Anak Air', 'Puhun Pintu Kabun', 'Puhun Tembok']],
                            ['nama' => 'Aur Birugo Tigo Baleh', 'kelurahanList' => ['Aur Kuning', 'Belakang Balok', 'Birugo', 'Kubu Tanjung', 'Ladang Cakiah', 'Sapiran', 'Pariangan']],
                        ],
                    ],
                    [
                        'nama' => 'Kota Payakumbuh',
                        'kecamatanList' => [
                            ['nama' => 'Payakumbuh Barat', 'kelurahanList' => ['Daya Bangun', 'Ibuah', 'Koto Nan Gadang', 'Parit Rantang', 'Sarilamak', 'Tanjung Pauh']],
                            ['nama' => 'Payakumbuh Selatan', 'kelurahanList' => ['Balai Nan Duo', 'Koto Baru', 'Ranah', 'Sawah Padang']],
                            ['nama' => 'Payakumbuh Timur', 'kelurahanList' => ['Balai Jariang', 'Koto Panjang', 'Padang Tangah', 'Payolansek', 'Talang']],
                            ['nama' => 'Payakumbuh Utara', 'kelurahanList' => ['Bulakan Balai Kandi', 'Langsat Kadap', 'Labuh Baru', 'Padang Kaduduak', 'Taluk']],
                            ['nama' => 'Lamposi Tigo Nagori', 'kelurahanList' => ['Koto Tangah Batu Hampar', 'Kubu Gadang', 'Lamposi', 'Sungai Durian', 'Tiakar']],
                        ],
                    ],
                    [
                        'nama' => 'Kota Solok',
                        'kecamatanList' => [
                            ['nama' => 'Lubuk Sikarah', 'kelurahanList' => ['Aro IV Korong', 'IX Korong', 'KTK', 'Lubuk Sikarah', 'Sinapa Piliang', 'Tanah Garam', 'VI Suku']],
                            ['nama' => 'Tanjung Harapan', 'kelurahanList' => ['Kampai Tabu Karambia', 'Koto Panjang', 'Laing', 'Nan Balimo', 'PPA', 'Tanjung Paku']],
                        ],
                    ],
                    [
                        'nama' => 'Kota Pariaman',
                        'kecamatanList' => [
                            ['nama' => 'Pariaman Barat', 'kelurahanList' => ['Desa Apar', 'Desa Bato', 'Desa Cubadak']],
                            ['nama' => 'Pariaman Selatan', 'kelurahanList' => ['Kurai Taji', 'Pauh Barat', 'Pauh Timur']],
                            ['nama' => 'Pariaman Timur', 'kelurahanList' => ['Kampung Baru', 'Kampung Perak', 'Naras Hilir']],
                            ['nama' => 'Pariaman Utara', 'kelurahanList' => ['Ampalu', 'Mangguang', 'Sintuk']],
                        ],
                    ],
                    [
                        'nama' => 'Kota Padang Panjang',
                        'kecamatanList' => [
                            ['nama' => 'Padang Panjang Barat', 'kelurahanList' => ['Bukit Surungan', 'Kampung Manggis', 'Pasar Usang', 'Silaing Bawah', 'Tanah Hitam']],
                            ['nama' => 'Padang Panjang Timur', 'kelurahanList' => ['Ekor Lubuk', 'Ganting', 'Guguk Malintang', 'Koto Katik', 'Ngalau', 'Sigando', 'Silaing Atas', 'Tanah Pak Lambik']],
                        ],
                    ],
                    [
                        'nama' => 'Kota Sawahlunto',
                        'kecamatanList' => [
                            ['nama' => 'Barangin', 'kelurahanList' => ['Kolok Nan Duo', 'Sikalang', 'Talawi Hilir']],
                            ['nama' => 'Lembah Segar', 'kelurahanList' => ['Durian', 'Pasar', 'Rantih']],
                            ['nama' => 'Silungkang', 'kelurahanList' => ['Muaro Kalaban', 'Silungkang Duo', 'Silungkang Oso', 'Silungkang Tigo']],
                            ['nama' => 'Talawi', 'kelurahanList' => ['Kumbayau', 'Salak', 'Talawi Hilir', 'Talawi Mudik']],
                        ],
                    ],
                    [
                        'nama' => 'Kab. Padang Pariaman',
                        'kecamatanList' => [
                            ['nama' => '2x11 Enam Lingkung', 'kelurahanList' => ['Koto Tinggi', 'Parit Malintang', 'Sicincin']],
                            ['nama' => '2x11 Kayu Tanam', 'kelurahanList' => ['Anduring', 'Kapalo Hilalang', 'Kayu Tanam']],
                            ['nama' => 'Batang Anai', 'kelurahanList' => ['Kasang', 'Ketaping', 'Sungai Buluh']],
                            ['nama' => 'Batang Gasan', 'kelurahanList' => ['Batu Kalang', 'Gasan Gadang', 'Sungai Asam']],
                            ['nama' => 'Enam Lingkung', 'kelurahanList' => ['Koto Tinggi', 'Parit Malintang']],
                            ['nama' => 'IV Koto Aur Malintang', 'kelurahanList' => ['Aur Malintang', 'Balah Aia', 'Koto Baru']],
                            ['nama' => 'Lubuk Alung', 'kelurahanList' => ['Lubuk Alung', 'Pacung', 'Pasir Laweh']],
                            ['nama' => 'Nan Sabaris', 'kelurahanList' => ['Aie Tajun', 'Kapuh', 'Sunur']],
                            ['nama' => 'Padang Sago', 'kelurahanList' => ['Guguak', 'Lubuk Jantan', 'Padang Sago']],
                            ['nama' => 'Patamuan', 'kelurahanList' => ['Campago', 'Kapundung', 'Patamuan']],
                            ['nama' => 'Sintuk Toboh Gadang', 'kelurahanList' => ['Katapiang', 'Sintuk', 'Toboh Gadang']],
                            ['nama' => 'Sungai Geringging', 'kelurahanList' => ['Batu Hampar', 'Kuranji Hilir', 'Sungai Geringging']],
                            ['nama' => 'Sungai Limau', 'kelurahanList' => ['Asam Kumbang', 'Koto Hilalang', 'Sungai Limau']],
                            ['nama' => 'V Koto Kampung Dalam', 'kelurahanList' => ['Koto Dalam', 'Limau Purut', 'Sungai Asam']],
                            ['nama' => 'V Koto Timur', 'kelurahanList' => ['Koto Baru', 'Limau Sundai', 'Sungai Sariak']],
                            ['nama' => 'VII Koto Sungai Sarik', 'kelurahanList' => ['Batu Basa', 'Padang Laweh', 'Sungai Sarik']],
                        ],
                    ],
                    [
                        'nama' => 'Kab. Agam',
                        'kecamatanList' => [
                            ['nama' => 'Ampek Angkek', 'kelurahanList' => ['Balai Gurah', 'Biaro Gadang', 'Lambah']],
                            ['nama' => 'Ampek Nagari', 'kelurahanList' => ['Bawan', 'Durian Gadang', 'Sitalang']],
                            ['nama' => 'Banuhampu', 'kelurahanList' => ['Cingkariang', 'Ladang Laweh', 'Pakan Sinayan']],
                            ['nama' => 'Baso', 'kelurahanList' => ['Baso', 'Koto Baru', 'Tabek Panjang']],
                            ['nama' => 'Canduang', 'kelurahanList' => ['Bukik Batabuah', 'Canduang Koto Laweh', 'Lasi']],
                            ['nama' => 'Kamang Magek', 'kelurahanList' => ['Kamang Hilir', 'Kamang Mudik', 'Magek']],
                            ['nama' => 'Lubuk Basung', 'kelurahanList' => ['Kampung Pinang', 'Lubuk Basung', 'Manggopoh']],
                            ['nama' => 'Malalak', 'kelurahanList' => ['Malalak Barat', 'Malalak Selatan', 'Malalak Timur']],
                            ['nama' => 'Matur', 'kelurahanList' => ['Matur Hilir', 'Matur Mudik', 'Tigo Koto Silungkang']],
                            ['nama' => 'Palembayan', 'kelurahanList' => ['Baringin', 'Palembayan', 'Sungai Janiah']],
                            ['nama' => 'Palupuh', 'kelurahanList' => ['Koto Rantang', 'Palupuh', 'Pasia Laweh']],
                            ['nama' => 'Sungai Pua', 'kelurahanList' => ['Batu Palano', 'Padang Laweh', 'Sungai Pua']],
                            ['nama' => 'Tanjung Raya', 'kelurahanList' => ['Bayur', 'Maninjau', 'Tanjung Sani']],
                            ['nama' => 'Tilatang Kamang', 'kelurahanList' => ['Gadut', 'Kapau', 'Koto Tangah']],
                        ],
                    ],
                    [
                        'nama' => 'Kab. Tanah Datar',
                        'kecamatanList' => [
                            ['nama' => 'Batipuh', 'kelurahanList' => ['Batipuh Baruah', 'Guguak Malalo', 'Pitalah']],
                            ['nama' => 'Batipuh Selatan', 'kelurahanList' => ['Gunung Rajo', 'Sumpur', 'Tanjung Barulak']],
                            ['nama' => 'Limo Kaum', 'kelurahanList' => ['Balimbing', 'Baringin', 'Lima Kaum']],
                            ['nama' => 'Lintau Buo', 'kelurahanList' => ['Buo', 'Lintau', 'Pangian']],
                            ['nama' => 'Lintau Buo Utara', 'kelurahanList' => ['Balai Tangah', 'Mungo', 'Tigo Jangko']],
                            ['nama' => 'Padang Ganting', 'kelurahanList' => ['Atar', 'Padang Ganting', 'Sungayang']],
                            ['nama' => 'Pariangan', 'kelurahanList' => ['Pariangan', 'Sawah Tangah', 'Tabek']],
                            ['nama' => 'Rambatan', 'kelurahanList' => ['Rambatan', 'Simawang', 'Tigo Balai']],
                            ['nama' => 'Salimpuang', 'kelurahanList' => ['Lawang Mandahiling', 'Salimpuang', 'Sumanik']],
                            ['nama' => 'Sungai Tarab', 'kelurahanList' => ['Koto Baru', 'Padang Laweh', 'Sungai Tarab']],
                            ['nama' => 'Sungayang', 'kelurahanList' => ['Andaleh', 'Minangkabau', 'Sungayang']],
                        ],
                    ],
                ],
            ],
            [
                'provinsi' => 'Riau',
                'kotaList' => [
                    [
                        'nama' => 'Kota Pekanbaru',
                        'kecamatanList' => [
                            ['nama' => 'Tampan', 'kelurahanList' => ['Delima', 'Sidomulyo Barat', 'Simpang Baru', 'Tuah Karya']],
                            ['nama' => 'Marpoyan Damai', 'kelurahanList' => ['Maharatu', 'Sidomulyo Timur', 'Tangkerang Barat', 'Tangkerang Tengah']],
                            ['nama' => 'Bukit Raya', 'kelurahanList' => ['Simpang Tiga', 'Tangkerang Selatan', 'Tangkerang Utara']],
                        ],
                    ],
                ],
            ],
            [
                'provinsi' => 'DKI Jakarta',
                'kotaList' => [
                    [
                        'nama' => 'Kota Jakarta Selatan',
                        'kecamatanList' => [
                            ['nama' => 'Kebayoran Baru', 'kelurahanList' => ['Gandaria Utara', 'Gunung', 'Kramat Pela', 'Melawai', 'Pulo', 'Rawa Barat', 'Selong']],
                            ['nama' => 'Cilandak', 'kelurahanList' => ['Cilandak Barat', 'Cipete Selatan', 'Gandaria Selatan', 'Pondok Labu']],
                        ],
                    ],
                    [
                        'nama' => 'Kota Jakarta Barat',
                        'kecamatanList' => [
                            ['nama' => 'Kebon Jeruk', 'kelurahanList' => ['Duri Kepa', 'Kedoya Selatan', 'Kedoya Utara', 'Kebon Jeruk']],
                        ],
                    ],
                ],
            ],
            [
                'provinsi' => 'Jawa Barat',
                'kotaList' => [
                    [
                        'nama' => 'Kota Bandung',
                        'kecamatanList' => [
                            ['nama' => 'Coblong', 'kelurahanList' => ['Cipaganti', 'Dago', 'Lebak Siliwangi', 'Lebakgede', 'Sadangserang']],
                        ],
                    ],
                ],
            ],
        ];

        $rows = [];
        foreach ($dataWilayah as $prov) {
            $provName = $prov['provinsi'];
            foreach ($prov['kotaList'] as $kota) {
                $kotaName = $kota['nama'];
                foreach ($kota['kecamatanList'] as $kec) {
                    $kecName = $kec['nama'];
                    foreach ($kec['kelurahanList'] as $kelName) {
                        $rows[] = [
                            'provinsi' => $provName,
                            'kota_kabupaten' => $kotaName,
                            'kecamatan' => $kecName,
                            'kelurahan' => $kelName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        IndonesiaRegion::query()->truncate();

        foreach (array_chunk($rows, 100) as $chunk) {
            IndonesiaRegion::query()->insert($chunk);
        }
    }
}
