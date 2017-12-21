<?php

namespace Itk\AarhusDataBundle\Service;

use Doctrine\ORM\EntityManager;
use Os2Display\CoreBundle\Events\CronEvent;
use Symfony\Component\Translation\TranslatorInterface;
use GuzzleHttp;

class DataService
{
    private $entityManager;
    private $translator;

    /**
     * DataService constructor.
     */
    public function __construct(
        EntityManager $entityManager,
        TranslatorInterface $translator
    ) {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
    }

    /**
     * CronEvent event listener.
     *
     * @param CronEvent $event
     */
    public function onCron(CronEvent $event)
    {
        $this->processDataFeeds();
    }

    /**
     * Processes the slides with slide_type: itk-aarhus-data.
     */
    public function processDataFeeds()
    {
        $cache = [];

        $data = null;

        $slides = $this->entityManager
            ->getRepository('Os2DisplayCoreBundle:Slide')
            ->findBySlideType('itk-aarhus-data');

        foreach ($slides as $slide) {
            $options = $slide->getOptions();

            if (isset($options['data_function'])) {
                if (isset($cache[$options['data_function']])) {
                    $data = $cache[$options['data_function']];
                } else {
                    $data = $this->dataFunction($options['data_function']);
                    $cache[$options['data_function']] = $data;
                }
            } else {
                $data = [];
            }

            // Only set data if it is non-empty
            if (!empty($data)) {
                $slide->setExternalData($data);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Get an array of available data functions.
     *
     * @return array
     */
    public function getAvailableDataFunctions()
    {
        return [
            'data_function.odaa-dokk1.all' => (object)[
                'id' => 'data_function.odaa-dokk1.all',
                'label' => $this->translate('data_function.odaa-dokk1.all'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.temperature' => (object)[
                'id' => 'data_function.odaa-dokk1.temperature',
                'label' => $this->translate(
                    'data_function.odaa-dokk1.temperature'
                ),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.daylight' => (object)[
                'id' => 'data_function.odaa-dokk1.daylight',
                'label' => $this->translate(
                    'data_function.odaa-dokk1.daylight'
                ),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.sound' => (object)[
                'id' => 'data_function.odaa-dokk1.sound',
                'label' => $this->translate('data_function.odaa-dokk1.sound'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.humidity' => (object)[
                'id' => 'data_function.odaa-dokk1.humidity',
                'label' => $this->translate(
                    'data_function.odaa-dokk1.humidity'
                ),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.aarhus-waterfront.weather-1' => (object)[
                'id' => 'data_function.aarhus-waterfront.weather-1',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.weather-1'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.weather-2' => (object)[
                'id' => 'data_function.aarhus-waterfront.weather-2',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.weather-2'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.temperature' => (object)[
                'id' => 'data_function.aarhus-waterfront.temperature',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.temperature'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.humidity' => (object)[
                'id' => 'data_function.aarhus-waterfront.humidity',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.humidity'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.daylight' => (object)[
                'id' => 'data_function.aarhus-waterfront.daylight',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.daylight'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.pressure' => (object)[
                'id' => 'data_function.aarhus-waterfront.pressure',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.pressure'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.water_temperature' => (object)[
                'id' => 'data_function.aarhus-waterfront.water_temperature',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.water_temperature'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.water_distance' => (object)[
                'id' => 'data_function.aarhus-waterfront.water_distance',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.water_distance'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.wind_speed' => (object)[
                'id' => 'data_function.aarhus-waterfront.wind_speed',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.wind_speed'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
            'data_function.aarhus-waterfront.rain' => (object)[
                'id' => 'data_function.aarhus-waterfront.rain',
                'label' => $this->translate(
                    'data_function.aarhus-waterfront.rain'
                ),
                'group' => $this->translate('group.aarhus'),
            ],
        ];
    }

    /**
     * Calls the relevant feed function.
     *
     * @param $functionName
     * @return array|null
     */
    private function dataFunction($functionName)
    {
        $data = [];

        switch ($functionName) {
            case 'data_function.odaa-dokk1.all':
                $data = $this->odaaDokk1MeasuresDataFunction(null);
                break;
            case 'data_function.odaa-dokk1.temperature':
                $data = $this->odaaDokk1MeasuresDataFunction('temperature');
                break;
            case 'data_function.odaa-dokk1.daylight':
                $data = $this->odaaDokk1MeasuresDataFunction('daylight');
                break;
            case 'data_function.odaa-dokk1.sound':
                $data = $this->odaaDokk1MeasuresDataFunction('sound');
                break;
            case 'data_function.odaa-dokk1.humidity':
                $data = $this->odaaDokk1MeasuresDataFunction('humidity');
                break;
            case 'data_function.aarhus-library-school-sun-energy':
                $data = $this->aarhusLibraryAndSchoolSunEnergyProduce();
                break;
            case 'data_function.aarhus-waterfront.weather-1':
                $data = $this->aarhusWaterfront(['water_temperature', 'water_distance', 'wind_speed', 'rain']);
                break;
            case 'data_function.aarhus-waterfront.weather-2':
                $data = $this->aarhusWaterfront(['temperature', 'humidity', 'daylight', 'pressure']);
                break;
            case 'data_function.aarhus-waterfront.temperature':
                $data = $this->aarhusWaterfront(['temperature']);
                break;
            case 'data_function.aarhus-waterfront.humidity':
                $data = $this->aarhusWaterfront(['humidity']);
                break;
            case 'data_function.aarhus-waterfront.daylight':
                $data = $this->aarhusWaterfront(['daylight']);
                break;
            case 'data_function.aarhus-waterfront.pressure':
                $data = $this->aarhusWaterfront(['pressure']);
                break;
            case 'data_function.aarhus-waterfront.water_temperature':
                $data = $this->aarhusWaterfront(['water_temperature']);
                break;
            case 'data_function.aarhus-waterfront.water_distance':
                $data = $this->aarhusWaterfront(['water_distance']);
                break;
            case 'data_function.aarhus-waterfront.wind_speed':
                $data = $this->aarhusWaterfront(['wind_speed']);
                break;
            case 'data_function.aarhus-waterfront.rain':
                $data = $this->aarhusWaterfront(['rain']);
                break;
        }

        return $data;
    }

    /**
     * Translate the key.
     *
     * @param $key
     * @return string
     */
    private function translate($key)
    {
        return $this->translator->trans($key, [], 'ItkAarhusDataBundle');
    }

    /**
     * @return array
     */
    public function aarhusWaterfront($ids)
    {
        $data = [];

        $sensors = ['0004A30B001E1694', '0004A30B001E307C', '0004A30B001E8EA2'];

        $input = [];

        foreach ($sensors as $sensor) {
            try {
                $client = new GuzzleHttp\Client();
                $res = $client->request(
                    'GET',
                    'http://things.hulk.aakb.dk/api/recent?sensor='.$sensor,
                    ['timeout' => 2]
                );

                $body = $res->getBody()->getContents();

                $input[$sensor] = json_decode($body);
            } catch (\Exception $e) {
                return null;
            }
        }

        if (in_array('water_temperature', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.water_temperature'),
                    'unit' => $this->translate('unit.temperature'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E1694']
                    ) ?
                        $input['0004A30B001E1694']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E1694']->sensor_water_temperature_value
                    ),
                ]
            );
        }

        if (in_array('water_distance', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.water_distance'),
                    'unit' => $this->translate('unit.water_distance'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E307C']
                    ) ?
                        $input['0004A30B001E307C']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E307C']->sensor_distance_to_water_value
                    ),
                ]
            );
        }

        if (in_array('wind_speed', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.wind_speed'),
                    'unit' => $this->translate('unit.wind_speed'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E8EA2']
                    ) ?
                        $input['0004A30B001E8EA2']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E8EA2']->sensor_wind_speed_value
                    ),
                ]
            );
        }

        if (in_array('rain', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.rain'),
                    'unit' => $this->translate('unit.rain'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E8EA2']
                    ) ?
                        $input['0004A30B001E8EA2']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E8EA2']->sensor_rain_value
                    ),
                ]
            );
        }

        if (in_array('temperature', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.temperature'),
                    'unit' => $this->translate('unit.temperature'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E8EA2']
                    ) ?
                        $input['0004A30B001E8EA2']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E8EA2']->sensor_temperature_value
                    ),
                ]
            );
        }

        if (in_array('humidity', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.humidity'),
                    'unit' => $this->translate('unit.humidity'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E8EA2']
                    ) ?
                        $input['0004A30B001E8EA2']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E8EA2']->sensor_humidity_value
                    ),
                ]
            );
        }

        if (in_array('daylight', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.daylight'),
                    'unit' => $this->translate('unit.daylight'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E8EA2']
                    ) ?
                        $input['0004A30B001E8EA2']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E8EA2']->sensor_lux_value
                    ),
                ]
            );
        }

        if (in_array('pressure', $ids)) {
            array_push(
                $data,
                [
                    'name' => $this->translate('field.pressure'),
                    'unit' => $this->translate('unit.pressure'),
                    'location' => $this->translate(
                        'location.aarhus_waterfront'
                    ),
                    'timestamp' => array_key_exists(
                        'sensor_ts',
                        $input['0004A30B001E8EA2']
                    ) ?
                        $input['0004A30B001E8EA2']->sensor_ts : null,
                    'value' => round(
                        $input['0004A30B001E8EA2']->sensor_pressure_value * 0.01
                    ),
                ]
            );
        }

        return $data;
    }

    /**
     * Gets sun energy production from schools and libraries in Aarhus.
     * http://www.odaa.dk/api/3/action/datastore_search?resource_id=251528ca-8ec9-4b70-9960-83c4d0c4e7b6
     *
     * @return array|null
     */
    public function aarhusLibraryAndSchoolSunEnergyProduce()
    {
        $data = [];
        $inputCurrent = null;
        $inputHistorical = null;
        $time = null;

        try {
            $client = new GuzzleHttp\Client();
            $res = $client->request(
                'GET',
                'http://www.odaa.dk/api/3/action/datastore_search?resource_id=251528ca-8ec9-4b70-9960-83c4d0c4e7b6',
                ['timeout' => 2]
            );

            $body = $res->getBody()->getContents();

            $inputCurrent = json_decode($body);
        } catch (\Exception $e) {
            return null;
        }

        if ($inputCurrent === false ||
            !isset($inputCurrent->result) ||
            !isset($inputCurrent->result->records)) {
            return null;
        }

        $sumCurrent = 0;
        $sumToday = 0;

        foreach ($inputCurrent->result->records as $record) {
            $sumCurrent = $sumCurrent + $record->current;
            $sumToday = $sumToday + $record->daily;
        }

        $data[0] = (object)[
            'name' => $this->translate('field.energy_current'),
            'id' => 'current',
            'value' => floor($sumCurrent / 1000),
            'timestamp' => $time,
            'unit' => $this->translate('unit.energy_current'),
        ];

        $data[1] = (object)[
            'name' => $this->translate('field.energy_today'),
            'id' => 'today',
            'value' => floor($sumToday / 1000),
            'timestamp' => $time,
            'unit' => $this->translate('unit.energy_today'),
        ];

        /*
                $data[2] = (object)[
                    'name' => $this->translate('field.energy_yesterday'),
                    'id' => 'yesterday',
                    'value' => 0,
                    'timestamp' => $time,
                    'unit' => $this->translate('unit.energy_yesterday'),
                ];
        */

        return $data;
    }

    /**
     * Gets temperature, daylight, volume, humidity from Dokk1
     * http://www.odaa.dk/api/3/action/datastore_search?resource_id=e123e70c-9d13-461e-8715-f06ec41dd3cf
     *
     * @return array|null
     */
    public function odaaDokk1MeasuresDataFunction($field)
    {
        $input = null;

        try {
            $client = new GuzzleHttp\Client();
            $res = $client->request(
                'GET',
                'http://www.odaa.dk/api/3/action/datastore_search?resource_id=e123e70c-9d13-461e-8715-f06ec41dd3cf',
                ['timeout' => 2]
            );

            $body = $res->getBody()->getContents();

            $input = json_decode($body);
        } catch (\Exception $e) {
            return null;
        }

        if ($input === false || !isset($input->result) || !isset($input->result->records)) {
            return null;
        }

        $data = [];

        $extractValues = [
            'temperature' => 'TCA',
            'daylight' => 'LUM',
            'sound' => 'MCP',
            'humidity' => 'HUMA',
        ];

        foreach ($extractValues as $key => $value) {
            if ($field == null || $field == $key) {
                $item = array_filter(
                    $input->result->records,
                    function ($item) use (&$value) {
                        return $item->sensor == $value;
                    }
                );

                if (empty($item)) {
                    continue;
                }

                $item = reset($item);

                array_push(
                    $data,
                    [
                        'name' => $this->translate('field.'.$key),
                        'unit' => $this->translate('unit.'.$key),
                        'location' => $this->translate('location.odaa-dokk1'),
                        'timestamp' => $item->time,
                        'value' => round($item->val),
                    ]
                );
            }
        }

        return $data;
    }

}
