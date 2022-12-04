<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class WeatherController extends AbstractController
{
    /**
     * @throws Throwable
     */
    #[Route('/weather')]
    public function index(): Response
    {
        $this->parseGismeteo();
        $weather = [];
        $weather['sinoptik'] = $this->parseSinoptik();
        $weather['meteo'] = $this->parseMeteo();
        $weather['meteoProg'] = $this->parseMeteoProg();
        $weather['gismeteo'] = $this->parseGismeteo();

        $callback = fn($key, $val): array => [date('d', strtotime(" +$key day")) => $val];

        foreach ($weather as $key => $provider) {
            $weather[$key] = array_map($callback, array_keys($provider), array_values($provider));
        }

        return $this->render('weather.html.twig', [
            'data' => $weather,
        ]);
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function parseSinoptik(): array
    {
        $crawler = new Crawler();
        $url = 'https://sinoptik.ua/погода-старый-косов/10-дней';
        $temperatures = [];
        $crawler->addHtmlContent(file_get_contents($url));
        for ($i = 1; $i <= 10; $i++) {
            $temperatures[$i - 1]['min'] = intval($crawler->filter("#bd$i > .temperature > .min > span")->text());
            $temperatures[$i - 1]['max'] = intval($crawler->filter("#bd$i > .temperature > .max > span")->text());
        }

        return $temperatures;
    }

    /**
     * @return array
     */
    public function parseMeteo(): array
    {
        $crawler = new Crawler();
        $url = 'https://meteo.ua/ua/16868/staryy-kosov/10-days';
        $temperatures = [];
        $crawler->addHtmlContent(file_get_contents($url));
        $degrees = $crawler->filter('.menu-basic__degree');
        foreach ($degrees as $key => $degree) {
            $degree = explode(' ° ..', $degree->textContent);
            $temperatures[$key]['min'] = intval($degree[0]);
            $temperatures[$key]['max'] = intval($degree[1]);
        }

        return $temperatures;
    }

    public function parseMeteoProg(): array
    {
        $crawler = new Crawler();
        $url = 'https://www.meteoprog.com/ua/review/Staryi%20kosiv-ivanofrankivska/';
        $temperatures = [];
        $crawler->addHtmlContent(file_get_contents($url));
        $crawler->filter('.swiper-slide > .thumbnail-item__temperature')
            ->each(function (Crawler $node, $key) use (&$temperatures) {
                $temps = explode(' ', $node->last()->text());
                $temperatures[$key]['max'] = intval($temps[1]);
                $temperatures[$key]['min'] = intval($temps[0]);
            });

        return array_slice($temperatures, 0, 10);
    }

    public function parseGismeteo(): array
    {
        $crawler = new Crawler();
        $url = 'https://www.gismeteo.ua/weather-staryi-kosiv-83380/10-days/';
        $temperatures = [];
        $crawler->addHtmlContent(file_get_contents($url));
        $crawler->filter('.ten-days > .values > .style_size_m')->each(function (Crawler $node, $key) use (&$temperatures) {
            if ($key > 9) {
                return;
            }
            $temperatures[$key]['max'] = (int)$node->filter('.unit_temperature_c')->text();
            $temperatures[$key]['min'] = (int)str_replace('−', '-', $node->filter('.unit_temperature_c')->last()->text());
        });

        return $temperatures;
    }
}