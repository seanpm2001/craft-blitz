<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use putyourlightson\blitz\Blitz;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CacheController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @var bool Disable Snaptcha validation
     */
    public $enableSnaptchaValidation = false;

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @throws ForbiddenHttpException
     */
    public function beforeAction($action)
    {
        parent::beforeAction($action);

        $request = Craft::$app->getRequest();

        // Require permission if posted from utility
        if ($request->getIsPost() && $request->getParam('utility')) {
            $this->requirePermission('blitz:cache-utility');
        }
        else {
            // Verify API key
            $key = $request->getParam('key');
            $apiKey = Craft::parseEnv(Blitz::$plugin->settings->apiKey);

            if (empty($key) || empty($apiKey) || $key != $apiKey) {
                throw new ForbiddenHttpException('Unauthorised access.');
            }
        }

        return true;
    }

    /**
     * Clears the cache.
     *
     * @return Response
     */
    public function actionClear(): Response
    {
        Blitz::$plugin->clearCache->clear();

        return $this->_getResponse('Blitz cache successfully cleared.');
    }

    /**
     * Flushes the cache.
     *
     * @return Response
     */
    public function actionFlush(): Response
    {
        Blitz::$plugin->clearCache->clear();
        Blitz::$plugin->flushCache->flush();

        return $this->_getResponse('Blitz cache successfully flushed.');
    }

    /**
     * Purges the cache.
     *
     * @return Response
     */
    public function actionPurge(): Response
    {
        Blitz::$plugin->cachePurger->purgeAll();

        return $this->_getResponse('Blitz cache successfully purged.');
    }

    /**
     * Warms the cache.
     *
     * @return Response
     */
    public function actionWarm(): Response
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            return $this->_getResponse('Blitz caching is disabled.');
        }

        Blitz::$plugin->clearCache->clear();

        // Create warm cache job before flushing the cache
        Blitz::$plugin->warmCache->warmAll();

        Blitz::$plugin->flushCache->flush();

        return $this->_getResponse('Blitz cache successfully queued for warming.');
    }

    /**
     * Refreshes expired cache.
     *
     * @return Response
     */
    public function actionRefreshExpired(): Response
    {
        Blitz::$plugin->refreshCache->refreshExpiredCache();

        return $this->_getResponse('Expired Blitz cache successfully refreshed.');
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a response.
     *
     * @param string $message
     *
     * @return Response
     */
    private function _getResponse(string $message)
    {
        $request = Craft::$app->getRequest();

        // If front-end site or JSON request
        if (Craft::$app->getView()->templateMode == View::TEMPLATE_MODE_SITE || $request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('blitz', $message));

        return $this->redirectToPostedUrl();
    }
}
