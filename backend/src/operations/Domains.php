<?php

namespace Operations;

require '../vendor/autoload.php';

/**
 * This class provides functions for retrieving and modifying domains.
 */
class Domains
{
    /** @var \Monolog\Logger */
    private $logger;

    /** @var \PDO */
    private $db;

    /** @var \Slim\Container */
    private $c;

    public function __construct(\Slim\Container $c)
    {
        $this->logger = $c->logger;
        $this->db = $c->db;
        $this->c = $c;
    }

    /**
     * Get a list of domains according to filter criteria
     * 
     * @param   $pi         PageInfo object, which is also updated with total page number
     * @param   $userId     Id of the user for which the table should be retrieved
     * @param   $query      Search query to search in the domain name, null for no filter
     * @param   $sorting    Sort string in format 'field-asc,field2-desc', null for default
     * @param   $type       Type to filter for, null for no filter
     * 
     * @return  array       Array with matching domains
     */
    public function getDomains(\Utils\PagingInfo &$pi, int $userId, ? string $query, ? string $sorting, ? string $type) : array
    {
        $ac = new \Operations\AccessControl($this->c);
        $userIsAdmin = $ac->isAdmin($userId);

        $queryStr = $query === null ? '%' : '%' . $query . '%';

        //Count elements
        if ($pi->pageSize === null) {
            $pi->totalPages = 1;
        } else {
            $query = $this->db->prepare('
                SELECT COUNT(*) AS total
                FROM domains D
                LEFT OUTER JOIN permissions P ON D.id = P.domain_id
                WHERE (P.user_id=:userId OR :userIsAdmin) AND
                (D.name LIKE :nameQuery) AND
                (D.type = :domainType OR :noTypeFilter)
            ');

            $query->bindValue(':userId', $userId, \PDO::PARAM_INT);
            $query->bindValue(':userIsAdmin', intval($userIsAdmin), \PDO::PARAM_INT);
            $query->bindValue(':nameQuery', $queryStr, \PDO::PARAM_STR);
            $query->bindValue(':domainType', (string)$type, \PDO::PARAM_STR);
            $query->bindValue(':noTypeFilter', intval($type === null), \PDO::PARAM_INT);

            $query->execute();
            $record = $query->fetch();

            $pi->totalPages = ceil($record['total'] / $pi->pageSize);
        }
        
        //Query and return result
        $ordStr = \Services\Database::makeSortingString($sorting, [
            'id' => 'D.id',
            'name' => 'D.name',
            'type' => 'D.type',
            'records' => 'records'
        ]);
        $pageStr = \Services\Database::makePagingString($pi);

        $query = $this->db->prepare('
            SELECT D.id,D.name,D.type,D.master,count(R.domain_id) AS records
            FROM domains D
            LEFT OUTER JOIN records R ON D.id = R.domain_id
            LEFT OUTER JOIN permissions P ON D.id = P.domain_id
            WHERE (P.user_id=:userId OR :userIsAdmin)
            GROUP BY D.id
            HAVING
            (D.name LIKE :nameQuery) AND
            (D.type=:domainType OR :noTypeFilter)'
            . $ordStr . $pageStr);

        $query->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $query->bindValue(':userIsAdmin', intval($userIsAdmin), \PDO::PARAM_INT);
        $query->bindValue(':nameQuery', $queryStr, \PDO::PARAM_STR);
        $query->bindValue(':domainType', (string)$type, \PDO::PARAM_STR);
        $query->bindValue(':noTypeFilter', intval($type === null), \PDO::PARAM_INT);

        $query->execute();

        $data = $query->fetchAll();

        return array_map(function ($item) {
            if ($item['type'] != 'SLAVE') {
                unset($item['master']);
            }
            $item['id'] = intval($item['id']);
            $item['records'] = intval($item['records']);
            return $item;
        }, $data);
    }

    /**
     * Get a list of domains according to filter criteria
     * 
     * @param   $name       Name of the new zone
     * @param   $type       Type of the new zone
     * @param   $master     Master for slave zones, otherwise null
     * 
     * @return  array       New domain entry
     */
    public function addDomain(string $name, string $type, ? string $master)
    {
        $this->db->beginTransaction();

        $query = $this->db->prepare('SELECT id FROM domains WHERE name=:name');
        $query->bindValue(':name', $name, \PDO::PARAM_STR);
        $query->execute();

        $record = $query->fetch();

        if ($record !== false) { // Domain already exists
            $this->db->rollBack();
            throw new \Exceptions\AlreadyExistentException();
        }

        if ($type === 'SLAVE') {
            $query = $this->db->prepare('INSERT INTO domains (name, type, master) VALUES(:name, :type, :master)');
            $query->bindValue(':master', $master, \PDO::PARAM_STR);
        } else {
            $query = $this->db->prepare('INSERT INTO domains (name, type) VALUES(:name, :type)');
        }
        $query->bindValue(':name', $name, \PDO::PARAM_STR);
        $query->bindValue(':type', $type, \PDO::PARAM_STR);
        $query->execute();


        $query = $this->db->prepare('SELECT id,name,type,master FROM domains WHERE name=:name');
        $query->bindValue(':name', $name, \PDO::PARAM_STR);
        $query->execute();

        $record = $query->fetch();
        $record['id'] = intval($record['id']);
        if ($type !== 'SLAVE') {
            unset($record['master']);
        }

        $this->db->commit();

        return $record;
    }
}
