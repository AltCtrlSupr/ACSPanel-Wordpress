<?php
namespace ACS\ACSPanelWordpressBundle\Event\Subscribers;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;

use ACS\ACSPanelWordpressBundle\Entity\WPSetup;

class EntitySubscriber implements EventSubscriber
{

    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getSubscribedEvents()
    {
        return array(
            'prePersist',
            'postPersist',
            'postUpdate',
            'preUpdate',
            'preRemove',
            'postRemove',
        );
    }

    public function preRemove(LifecycleEventArgs $args)
    {
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        if ($entity instanceof DB){
            $security = $this->container->get('security.context');
            $entity->setUser($security->getToken()->getUser());
        }
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        if ($entity instanceof DatabaseUser){
            $this->setUpdatedAtValue($entity);
        }
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        if ($entity instanceof DatabaseUser){
            $this->createUserInDatabase($entity);
            $this->setUserValue($entity);
        }
        if ($entity instanceof FosUser){
            $this->incrementUidSetting($entity);
            $this->incrementGidSetting($entity);
        }

        if ($entity instanceof FtpdUser){
            $setting_manager = $this->container->get('acs.setting_manager');
            $setting_manager->setInternalSetting('last_used_uid',$entity->getUid());
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        if ($entity instanceof DatabaseUser){
            $this->removeUserInDatabase($entity);
            $this->createUserInDatabase($entity);
            $this->setUpdatedAtValue($entity);
        }
        if ($entity instanceof Domain){
            $this->setUpdatedAtValue($entity);
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        if ($entity instanceof DatabaseUser){
            $this->removeUserInDatabase();
        }
    }

    private function createUserInDatabase($entity)
    {
        $admin_user = '';
        $admin_password = '';
        $settings = $entity->getDb()->getService()->getSettings();
        foreach ($settings as $setting){
            if($setting->getSettingKey() == 'admin_user')
                $admin_user = $setting->getValue();
            if($setting->getSettingKey() == 'admin_password')
                $admin_password = $setting->getValue();
        }
        $server_ip = $entity->getDb()->getService()->getIp();


        $config = new \Doctrine\DBAL\Configuration();

        $connectionParams = array(
            'user' => $admin_user,
            'password' => $admin_password,
            'host' => $server_ip,
            'driver' => 'pdo_mysql',
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

        $sql = "CREATE USER '".$this->getUsername()."'@'%' IDENTIFIED BY '".$this->getPassword()."'";
        $conn->executeQuery($sql);
        $sql = "GRANT ALL PRIVILEGES ON `".$this->getDb()."`.* TO '".$this->getUsername()."'@'%'";
        $conn->executeQuery($sql);
        $sql = "FLUSH PRIVILEGES";
        $conn->executeQuery($sql);
    }

    public function removeUserInDatabase($entity)
    {
        $admin_user = '';
        $admin_password = '';
        $settings = $entity->getDb()->getService()->getSettings();
        foreach ($settings as $setting){
            if($setting->getSettingKey() == 'admin_user')
                $admin_user = $setting->getValue();
            if($setting->getSettingKey() == 'admin_password')
                $admin_password = $setting->getValue();
        }
        $server_ip = $entity->getDb()->getService()->getIp();

        $config = new \Doctrine\DBAL\Configuration();
        //..
        $connectionParams = array(
            'user' => $admin_user,
            'password' => $admin_password,
            'host' => $server_ip,
            'driver' => 'pdo_mysql',
        );

        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
        $sql = "DROP USER '".$entity->getUsername()."'@'%';";

        $conn->executeQuery($sql);
    }

    private function setCreatedAtValue($entity)
    {
        if(!$entity->getCreatedAt())
        {
            $entity->createdAt = new \DateTime();
        }
    }

    private function setUpdatedAtValue($entity)
    {
        $entity->updatedAt = new \DateTime();
    }

    public function createDatabase($entity)
    {
        $admin_user = '';
        $admin_password = '';

        $settings = $entity->getService()->getSettings();

        foreach ($settings as $setting){
            if($setting->getSettingKey() == 'admin_user')
                $admin_user = $setting->getValue();
            if($setting->getSettingKey() == 'admin_password')
                $admin_password = $setting->getValue();
        }
        $server_ip = $entity->getService()->getIp();

        $config = new \Doctrine\DBAL\Configuration();
        //..
        $connectionParams = array(
            'user' => $admin_user,
            'password' => $admin_password,
            'host' => $server_ip,
            'driver' => 'pdo_mysql',
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

        $sql = "CREATE DATABASE IF NOT EXISTS ".$this->getName()." DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
        $conn->executeQuery($sql);

        return $this;

    }

    /**
     * @todo check for best way to get current user
     */
    public function setUserValue($entity)
    {
        if($entity->getUser())
            return;

        $service = $this->container->get('security.context');

        if(!$service->getToken())
            return;

        $user = $service->getToken()->getUser();
        return $entity->setUser($user);
    }

    public function removeDatabase($entity)
    {
        $admin_user = '';
        $admin_password = '';
        $settings = $entity->getService()->getSettings();
        foreach ($settings as $setting){
            if($setting->getSettingKey() == 'admin_user')
                $admin_user = $setting->getValue();
            if($setting->getSettingKey() == 'admin_password')
                $admin_password = $setting->getValue();
        }
        $server_ip = $entity->getService()->getIp();

        $config = new \Doctrine\DBAL\Configuration();

        $connectionParams = array(
            'user' => $admin_user,
            'password' => $admin_password,
            'host' => $server_ip,
            'driver' => 'pdo_mysql',
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

        $users = $entity->getDatabaseUsers();
        if(count($users)){
            foreach($users as $usr){
                $sql = "GRANT ALL PRIVILEGES ON `".$entity->getName()."` . * TO '".$usr->getUsername()."'@'%'";
                $conn->executeQuery($sql);
                $sql = "DROP USER '".$usr->getUsername()."'@'%'";
                $conn->executeQuery($sql);
            }
        }

        $sql = "DROP DATABASE IF EXISTS ".$entity->getName();
        $conn->executeQuery($sql);

        return $entity;

    }

    public function setFosUserUserValue($entity)
    {
        if($entity->getParentUser())
            return;


        $service = $this->container->get('security.context');

        if ($service) {
            if ($service->getToken()) {
                $user = $service->getToken()->getUser();
                // TODO: Get system user and set if its register from register form
                if($user != 'anon.'){
                    return $entity->setParentUser($user);
                }else{
                    // $system_user = new FosUser();
                    // $system_user->setId(1);
                    // return $this->setParentUser($system_user);
                }
            }
        }
    }

    public function setGidAndUidValues($entity)
    {
        $usertools = $this->container->get('acs.user.tools');

        $entity->setUid($usertools->getAvailableUid());
        $entity->setGid($usertools->getAvailableGid());
    }

    public function incrementUidSetting($entity)
    {
        $setting_manager = $this->container->get('acs.setting_manager');

        return $setting_manager->setInternalSetting('last_used_uid',$entity->getUid());
    }

   public function incrementGidSetting($entity)
    {
        $setting_manager = $this->container->get('acs.setting_manager');

        return $setting_manager->setInternalSetting('last_used_gid',$entity->getGid());
    }

}