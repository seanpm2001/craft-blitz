<?php

/**
 * Tests that cached web responses contain the correct headers and comments.
 */

use Mockery\MockInterface;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\RefreshCacheService;

beforeEach(function() {
    Blitz::$plugin->settings->includedUriPatterns = [
        [
            'siteId' => '',
            'uriPattern' => '.*',
        ],
    ];
    Blitz::$plugin->cacheStorage->deleteAll();
    Blitz::$plugin->set('refreshCache', Mockery::mock(RefreshCacheService::class . '[refresh]'));
});

afterAll(function() {
    Blitz::$plugin->cacheStorage->deleteAll();
});

test('Response contains the default cache control header when the page is not cacheable', function() {
    $response = sendRequest();
    Blitz::$plugin->cacheRequest->setDefaultCacheControlHeader();

    expect($response->headers->get('cache-control'))
        ->toEqual(Blitz::$plugin->settings->defaultCacheControlHeader);
});

test('Response contains the cache control header when the page is cacheable', function() {
    $response = sendRequest();

    expect($response->headers->get('cache-control'))
        ->toEqual(Blitz::$plugin->settings->cacheControlHeader);
});

test('Response contains the expired cache control header and the cache is refreshed when the page is expired', function() {
    sendRequest();

    // Must use a blank URI for this test!
    $siteUri = createSiteUri(uri: '');

    Blitz::$plugin->expireCache->expireUris([$siteUri]);

    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldReceive('refresh')->once();

    $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

    expect($response->headers->get('cache-control'))
        ->toEqual(Blitz::$plugin->settings->cacheControlHeaderExpired);
});

test('Response adds the “X-Powered-By” header once', function() {
    Craft::$app->config->general->sendPoweredByHeader = true;
    $response = sendRequest();

    expect($response->headers->get('x-powered-by'))
        ->toContainOnce('Blitz', 'Craft CMS');
});

test('Response overwrites the “X-Powered-By” header', function() {
    Craft::$app->config->general->sendPoweredByHeader = false;
    $response = sendRequest();

    expect($response->headers->get('x-powered-by'))
        ->toContainOnce('Blitz')
        ->not()->toContain('Craft CMS');
});

test('Response contains output comments when enabled', function() {
    foreach ([true, SettingsModel::OUTPUT_COMMENTS_SERVED] as $value) {
        Blitz::$plugin->settings->outputComments = $value;
        $response = sendRequest();

        expect($response->content)
            ->toContain('Cached by Blitz');
    }
});

test('Response does not contain output comments when disabled', function() {
    foreach ([false, SettingsModel::OUTPUT_COMMENTS_CACHED] as $value) {
        Blitz::$plugin->settings->outputComments = $value;
        $response = sendRequest();

        expect($response->content)
            ->not()->toContain('Served by Blitz on');
    }
});

test('Response with mime type has headers and does not contain output comments', function() {
    $output = createOutput();
    $siteUri = createSiteUri(uri: 'page.json');
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

    expect($response->headers->get('Content-Type'))
        ->toBe('application/json')
        ->and($response->content)
        ->toBe($output);
});

test('Response is encoded when compression is enabled', function() {
    $output = createOutput();
    $siteUri = createSiteUri();
    Blitz::$plugin->cacheStorage->compressCachedValues = true;
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    Craft::$app->getRequest()->headers->set('Accept-Encoding', 'deflate, gzip');
    $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

    expect($response->headers->get('Content-Encoding'))
        ->toBe('gzip')
        ->and(gzdecode($response->content))
        ->toBe($output);
});

test('Response is not encoded when compression is disabled', function() {
    $output = createOutput();
    $siteUri = createSiteUri();
    Blitz::$plugin->cacheStorage->compressCachedValues = false;
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    Craft::$app->getRequest()->headers->set('Accept-Encoding', 'deflate, gzip');
    $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

    expect($response->headers->get('Content-Encoding'))
        ->toBeNull()
        ->and($response->content)
        ->toContain($output);
});
