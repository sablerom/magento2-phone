<?php
namespace SableSoft\Phone\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AccountManagement as CustomerAccountManagement;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\InvalidEmailOrPasswordException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Model\Config\Share;
use SableSoft\Phone\Model\Config\Source\AuthMode;
use SableSoft\Phone\Model\Phone;

/**
 * Class AccountManagement
 *
 * @package SableSoft\Phone\Model
 */
class AccountManagement extends CustomerAccountManagement {

    /** @var \Magento\Customer\Api\CustomerRepositoryInterface */
    private $customerRepository;
    /** @var \Magento\Customer\Model\CustomerRegistry */
    private $customerRegistry;
    /** @var \Magento\Framework\Encryption\EncryptorInterface */
    private $encryptor;
    /** @var \Magento\Customer\Model\CustomerFactory */
    private $customerFactory;
    /** @var \Magento\Framework\Event\ManagerInterface */
    private $eventManager;
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;
    /** @var FilterBuilder */
    private $filterBuilder;
    /** @var \Magento\Store\Model\StoreManagerInterface */
    private $storeManager;
    /** @var Config  */
    protected $config;
    /** @var Phone  */
    protected $phone;
    /** @var Code */
    protected $code;

    /**
     * AccountManagement constructor.
     *
     * @param \Magento\Customer\Model\CustomerFactory                      $customerFactory
     * @param \Magento\Framework\Event\ManagerInterface                    $eventManager
     * @param \Magento\Store\Model\StoreManagerInterface                   $storeManager
     * @param \Magento\Framework\Math\Random                               $mathRandom
     * @param \Magento\Customer\Model\Metadata\Validator                   $validator
     * @param \Magento\Customer\Api\Data\ValidationResultsInterfaceFactory $validationResultsDataFactory
     * @param \Magento\Customer\Api\AddressRepositoryInterface             $addressRepository
     * @param \Magento\Customer\Api\CustomerMetadataInterface              $customerMetadataService
     * @param \Magento\Customer\Model\CustomerRegistry                     $customerRegistry
     * @param \Psr\Log\LoggerInterface                                     $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface             $encryptor
     * @param \Magento\Customer\Model\Config\Share                         $configShare
     * @param \Magento\Framework\Stdlib\StringUtils                        $stringHelper
     * @param \Magento\Customer\Api\CustomerRepositoryInterface            $customerRepository
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig
     * @param \Magento\Framework\Mail\Template\TransportBuilder            $transportBuilder
     * @param \Magento\Framework\Reflection\DataObjectProcessor            $dataProcessor
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Customer\Helper\View                                $customerViewHelper
     * @param \Magento\Framework\Stdlib\DateTime                           $dateTime
     * @param \Magento\Customer\Model\Customer                             $customerModel
     * @param \Magento\Framework\DataObjectFactory                         $objectFactory
     * @param \Magento\Framework\Api\ExtensibleDataObjectConverter         $extensibleDataObjectConverter
     * @param SearchCriteriaBuilder                                        $searchCriteriaBuilder
     * @param FilterBuilder                                                $filterBuilder
     * @param Config                                                       $config
     */
    public function __construct(
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Customer\Model\Metadata\Validator $validator,
        \Magento\Customer\Api\Data\ValidationResultsInterfaceFactory $validationResultsDataFactory,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Magento\Customer\Api\CustomerMetadataInterface $customerMetadataService,
        \Magento\Customer\Model\CustomerRegistry $customerRegistry,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Customer\Model\Config\Share $configShare,
        \Magento\Framework\Stdlib\StringUtils $stringHelper,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Reflection\DataObjectProcessor $dataProcessor,
        \Magento\Framework\Registry $registry,
        \Magento\Customer\Helper\View $customerViewHelper,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Customer\Model\Customer $customerModel,
        \Magento\Framework\DataObjectFactory $objectFactory,
        \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        Config $config,
        Phone $phone,
        Code $code
    ) {
        parent::__construct(
            $customerFactory, $eventManager, $storeManager,
            $mathRandom, $validator, $validationResultsDataFactory,
            $addressRepository, $customerMetadataService, $customerRegistry,
            $logger, $encryptor, $configShare, $stringHelper,
            $customerRepository, $scopeConfig, $transportBuilder,
            $dataProcessor, $registry, $customerViewHelper,
            $dateTime, $customerModel, $objectFactory,
            $extensibleDataObjectConverter
        );

        $this->customerRepository = $customerRepository;
        $this->customerRegistry = $customerRegistry;
        $this->encryptor = $encryptor;
        $this->customerFactory = $customerFactory;
        $this->eventManager = $eventManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->storeManager = $storeManager;

        $this->config = $config;
        $this->phone = $phone;
        $this->code = $code;
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function authenticate( $username, $password ) {
        $authMode = $this->config->getValue('auth');
        // find customer try:
        try {
            switch( $authMode ) {
                case AuthMode::MODE_CODE:
                case AuthMode::MODE_PHONE:
                    $customer = $this->customerByPhone( $username );
                    break;
                case AuthMode::MODE_BOTH:
                    $customer = $this->customerByPhoneOrEmail($username);
                    break;
                default:
                    $customer = $this->customerByEmail($username);
                    break;
            }
        } catch( NoSuchEntityException $e ) {
            throw new InvalidEmailOrPasswordException( __( 'Invalid login or password.' ) );
        }

        // password validate as phone code:
        if( $authMode === AuthMode::MODE_CODE ) {
            if( !$this->code->validate( $password ) )
                throw new InvalidEmailOrPasswordException(__('Invalid phone code.'));
        // default password validate:
        } else {
            $this->checkPasswordStrength( $password );
            $hash = $this->customerRegistry
                ->retrieveSecureData( $customer->getId() )->getPasswordHash();
            if( !$this->encryptor->validateHash( $password, $hash ) ) {
                throw new InvalidEmailOrPasswordException(__('Invalid login or password.'));
            }
        }

        if( $customer->getConfirmation() &&
            $this->isConfirmationRequired( $customer ) )
            throw new EmailNotConfirmedException(__('This account is not confirmed.'));

        $customerModel = $this->customerFactory->create()->updateData( $customer );
        $this->eventManager->dispatch(
            'customer_customer_authenticated',
            ['model' => $customerModel, 'password' => $password]
        );

        $this->eventManager->dispatch(
            'customer_data_object_login', [ 'customer' => $customer ]
        );

        return $customer;
    }

    /**
     * Find customer by email
     *
     * @param string $email - customer email
     * @return CustomerInterface
     * @throws NoSuchEntityException
     */
    protected function customerByEmail( string $email ) : CustomerInterface {
        try {
            return $this->customerRepository->get( $email );
        } catch( NoSuchEntityException $e ) {
            throw new NoSuchEntityException();
        } catch( \Exception $e ) {
            $this->logger->error( $e->getMessage() );
            throw new NoSuchEntityException();
        }
    }

    /**
     * Find customer by phone
     *
     * @param string $username Username
     * @return CustomerInterface
     * @throws NoSuchEntityException
     */
    protected function customerByPhone( string $username ) : CustomerInterface {
        $customer = $this->_customerByPhone( $username );
        if( false == $customer )
            throw new NoSuchEntityException();

        return $customer;
    }

    /**
     * Find customer by phone or email
     *
     * @param string $username Username
     * @return CustomerInterface
     * @throws NoSuchEntityException
     */
    protected function customerByPhoneOrEmail( string $username ) : CustomerInterface {
        $customer = $this->_customerByPhone( $username );
        if( false === $customer )
            try {
                $customer = $this->customerRepository->get( $username );
            } catch( NoSuchEntityException $e ) {
                throw new NoSuchEntityException();
            } catch( \Exception $e ) {
                $this->logger->error( $e->getMessage() );
            }
        if( false == $customer )
            throw new NoSuchEntityException();

        return $customer;
    }

    /**
     * Find a customer by phone
     *
     * @param string $phone Attribute Value
     * @return bool|CustomerInterface
     */
    protected function _customerByPhone( string $phone ) {
        try {
            $phoneAttribute = Config::ATTRIBUTE_PHONE;
            // add website filter:
            $websiteIdFilter = false;
            if( $this->config->getCustomerAccountShareScope() == Share::SHARE_WEBSITE )
                $websiteIdFilter[] = $this->filterBuilder
                    ->setField('website_id')
                    ->setConditionType('eq')
                    ->setValue( $this->storeManager->getStore()->getWebsiteId() )
                    ->create();
            // Add phone filter:
            $phoneFilter[] = $this->filterBuilder
                ->setField( $phoneAttribute )
                ->setConditionType('eq')
                ->setValue( $this->phone->setShort( $phone ) )
                ->create();
            // Build search criteria
            $searchCriteriaBuilder = $this->searchCriteriaBuilder->addFilters( $phoneFilter );
            if( is_array( $websiteIdFilter ) )
                $searchCriteriaBuilder->addFilters($websiteIdFilter);
            $searchCriteria = $searchCriteriaBuilder->create();
            // Retrieve the customer collection
            // and return customer if there was exactly one customer found
            $collection = $this->customerRepository->getList($searchCriteria);
            if( $collection->getTotalCount() == 1 )
                return $collection->getItems()[0];

        } catch( \Exception $e ) {
            $this->logger->error( $e->getMessage() );
        }

        return false;
    }
}