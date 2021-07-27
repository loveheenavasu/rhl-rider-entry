<?php
/**
 * Sprout Forms States plugin for Craft CMS 3.x
 *
 * US states fields for Sprout Forms
 *
 * @link      https://www.barrelstrengthdesign.com/
 * @copyright Copyright (c) 2018 Barrel Strength
 */

namespace barrelstrength\sproutformsusstates;

use barrelstrength\sproutforms\services\Fields;
use barrelstrength\sproutforms\events\RegisterFieldsEvent;
use barrelstrength\sproutformsusstates\integrations\sproutforms\fields\States;
use Craft;
use craft\base\Element;
use craft\helpers\Html;
use craft\elements\Entry as BaseEntry;
use craft\base\Plugin;

use yii\base\Event;
use barrelstrength\sproutforms\elements\Entry;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin as Commerce;

use craft\commerce\controllers\BaseFrontEndController;
use craft\commerce\events\ModifyCartInfoEvent;

class SproutFormsUsStates extends Plugin
{
    protected $_cart;
    protected $_cartVariable;
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        Craft::setAlias('@sproutformsusstatesicons', $this->getBasePath().'/web/icons');

        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELDS, static function(RegisterFieldsEvent $event) {
            $event->fields[] = new States();
        });
        
        Event::on(Entry::class, Entry::EVENT_AFTER_SAVE, function(Event $event) {
            if (Craft::$app->request->isSiteRequest)
            {
                // The Form Entry Element is available via the $event->sender attribute     
                $formEntryElement = $event->sender;

                $eventId = $formEntryElement->eventId;
                $eventId = explode('/', $eventId);
                $event = "";
                $note = "";
                if (is_array($eventId)) {
                    $event = \craft\elements\Entry::find()
                    ->section('events')
                    ->slug($eventId)
                    ->one();

                    if (!empty($event->id)) {
                        if( isset($formEntryElement->riderNumber1[1]) ){
                            $riderNumber = $formEntryElement->riderNumber1[1];

                            $riderNumbers = \craft\elements\Entry::find()
                                ->section('riderNumbers')
                                ->eventslist($event)
                                ->reservedNumber($riderNumber)
                                ->one();

                            if (empty($riderNumbers)) {
                                $id = $formEntryElement->id;
                                $firstName = $formEntryElement->firstName;
                                $lastName = $formEntryElement->lastName;
                                $class = $formEntryElement->classes->one();
                                $title = "Rider Entry Fees: ".$event->title;
                                $slug = "ref-".$event->slug;
                                $price = $class->fee;
                                $note = $firstName." ".$lastName.": ".$id;
                                $options['riderFormId'] = $id;
                                $options['eventId'] = $event->id;
                                $options['classId'] = $class->id;

                                $strA = explode(' ', $event->title);
                                $string = "";
                                foreach($strA as $words)
                                {
                                    $string = $string . $words[0];
                                }

                                $vTitle = $string." ".$class->title;
                                $vSku = strtolower($string)."-".$class->slug;

                                $acu = $formEntryElement->acuMotocrossLicense->getOptions();
                                if ($acu) {
                                    if ($acu[0]->selected) {
                                        $acuProduct = \craft\commerce\elements\Product::find()
                                        ->slug('acu-license')
                                        ->one();
                                        if ($acuProduct->defaultVariantId) {
                                            $this->customCart($acuProduct->defaultVariantId);
                                        }
                                    }
                                }
                                
                                $product = Product::find()
                                ->slug($slug)
                                ->one();
                                if (empty($product)) {
                                    // Create product
                                    $product = new Product();
                                    $product->title = $title;
                                    $product->slug = $slug;
                                    $product->typeId = 4;
                                    $product->enabled = true;
                                }
                                
                                if (!empty($product)) {
                                    $variants = $product->getVariants();

                                    // Create variant
                                    $variant = new Variant();
                                    $variant->title = $vTitle;
                                    $variant->sku = $vSku;
                                    $variant->price = $price;
                                    $variant->stock = 1;
                                    
                                    $variants[] = $variant;
                                    // Save product with variant
                                    $product->setVariants($variants);
                                    if(Craft::$app->elements->saveElement($product)) {
                                        sleep(2);
                                        $purchasableId = (int)$variant->id;
                                        $this->customCart($purchasableId,$note,$options);
                                        $this->createRiderNumberEntry($title,$class,$event,$riderNumber);
                                    } else {
                                        throw new \Exception("Couldn't save new bespoke product: " . print_r($product->getErrors(), true));
                                    }
                                } else {
                                    throw new \Exception("Product not found");
                                }
                            } else {
                                throw new \Exception("Rider number already occupied.");
                            }
                        } else {
                            throw new \Exception("Rider number not selected.");
                        }
                    }else{
                        throw new \Exception("Event not found.");
                    }
                }else{
                    throw new \Exception("Event not found.");
                }
            }
            
            if (Craft::$app->request->isCpRequest)
            {
                $formEntryElement = $event->sender;
            }
            
        });
    }

    public function customCart($purchasableId,$note = "",$options = []){
        $cart = Commerce::getInstance()->getCarts()->getCart(true);
        $cart->setFieldValuesFromRequest('fields');

        if ($purchasableId) {
            $qty = 1;

            if ($qty > 0) {
                $lineItem = Commerce::getInstance()->getLineItems()->resolveLineItem($cart->id, $purchasableId, $options);

                // New line items already have a qty of one.
                if ($lineItem->id) {
                    $lineItem->qty += $qty;
                } else {
                    $lineItem->qty = $qty;
                }

                $lineItem->note = $note;

                $cart->addLineItem($lineItem);

                return $this->_returnCart();
            }
        }
    }

    private function _returnCart()
    {
        $this->_cart = Commerce::getInstance()->getCarts()->getCart(true);
        // Allow validation of custom fields when passing this param
        $validateCustomFields = Commerce::getInstance()->getSettings()->validateCartCustomFieldsOnSubmission;

        // return true;
        // Do we want to validate fields submitted
        $customFieldAttributes = [];

        if ($validateCustomFields) {
            // $fields will be null so
            if ($submittedFields = $this->request->getBodyParam('fields')) {
                $this->_cart->setScenario(Element::SCENARIO_LIVE);
                $customFieldAttributes = array_keys($submittedFields);
            }
        }

        $attributes = array_merge($this->_cart->activeAttributes(), $customFieldAttributes);

        $updateCartSearchIndexes = Commerce::getInstance()->getSettings()->updateCartSearchIndexes;

        // Do not clear errors, as errors could be added to the cart before _returnCart is called.
        if (!$this->_cart->validate($attributes, false) || !Craft::$app->getElements()->saveElement($this->_cart, false, false, $updateCartSearchIndexes)) {
            $error = Craft::t('commerce', 'Unable to update cart.');
            $message = $this->request->getValidatedBodyParam('failMessage') ?? $error;

            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'error' => $error,
                    'errors' => $this->_cart->getErrors(),
                    'success' => !$this->_cart->hasErrors(),
                    'message' => $message,
                    $this->_cartVariable => $this->cartArray($this->_cart)
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                $this->_cartVariable => $this->_cart
            ]);

            $this->setFailFlash($error);

            return null;
        }

        if (($cartUpdatedNotice = $this->request->getParam('cartUpdatedNotice')) !== null) {
            $cartUpdatedMessage = Html::encode($cartUpdatedNotice);
            Craft::$app->getDeprecator()->log('cartUpdatedNotice', 'The `cartUpdatedNotice` param has been deprecated for `carts/*` requests. Use a hashed `successMessage` param instead.');
        } else {
            $cartUpdatedMessage = Craft::t('commerce', 'Cart updated.');
        }

        if ($this->request->getAcceptsJson()) {
            $message = $this->request->getValidatedBodyParam('successMessage') ?? $cartUpdatedMessage;

            return $this->asJson([
                'success' => !$this->_cart->hasErrors(),
                $this->_cartVariable => $this->cartArray($this->_cart),
                'message' => $message
            ]);
        }

        // $this->setSuccessFlash($cartUpdatedMessage);

        Craft::$app->getUrlManager()->setRouteParams([
            $this->_cartVariable => $this->_cart
        ]);

        return true;
    }

    public function createRiderNumberEntry($title = "",$class,$event,$riderNumber = 0){
        $section = Craft::$app->sections->getSectionByHandle('riderNumbers');
        if($section){
            $user = Craft::$app->getUser();
            $entry = new BaseEntry();
            $entry->sectionId = $section->id;
            $entry->typeId = 1;
            $entry->authorId = $user->id;
            $entry->enabled = true;
            $entry->title = $title;
            
            $entry->setFieldValues([
                'eventslist' => [$event->id],
                'classes' => [$class->id],
                'reservedNumber' => $riderNumber,
                'rider' => [$user->id],
            ]);

            $success = Craft::$app->elements->saveElement($entry);
            if (!$success) {
                Craft::error('Couldn’t save the entry "'.$entry->title.'"', __METHOD__);
            }
        }
    }
}