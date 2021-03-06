<?php

/**
 * @file
 *   Provides command line options for interacting with activeCollab API.
 */

namespace ActiveCollabConsole;

use ActiveCollabApi\ActiveCollabApi;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Dumper;

/**
 * Provides methods for interacting with the ActiveCollabApi.
 */
class ActiveCollabConsole extends ActiveCollabApi
{

  const VERSION = '0.1';

  /**
   * Constructor
   */
  public function __construct()
  {
    if (!$this->checkRequirements()) {
      return false;
    }
  }

  /**
   * Wrapper around calling the ActiveCollab API. Allows for checking cache
   * prior to calling API.
   */
  public function api($api_call, $cache = TRUE) {
    $yaml = new Parser();

    switch ($api_call) {
      case 'whoAmI':
      case 'getVersion':
        if (!$cache) {
          return $this->cacheSet(parent::$api_call(), 'version');
        }
        $version = $this->cacheGet('version');
        if (!$version) {
          $data = $this->cacheSet(parent::getVersion(), 'version');
          if ($api_call == 'getVersion') {
            return $data;
          }
          else {
            return $data['logged_user'];
          }
        }
        else {
          return $version;
        }
        break;
      case 'listPeople':
        if (!$cache) {
          return $this->cacheSet(parent::$api_call(), 'companies');
        }
        $companies = $this->cacheGet('companies');
        if (!$companies) {
          $data = $this->cacheSet(parent::listPeople(), 'companies');
          return $companies;
        }
        return $companies;
        break;
      default:
        return parent::$api_call();
        break;
      }
  }

  /**
   * Set cache.
   *
   * @param array $data
   * @param string $bin
   */
  private function cacheSet($data, $bin) {
    $file = __DIR__ . '/app/cache/' . $bin . '.yml';
    $dumper = new Dumper();
    // Convert any objects to arrays.
    $json  = json_encode($data);
    $array = json_decode($json, true);
    $yaml = $dumper->dump($array, 2);
    file_put_contents($file, $yaml);
  }

  /**
   * Get cache.
   *
   * @param string $bin
   */
  private function cacheGet($bin) {
    $yaml = new Parser();
    $fs = new Filesystem();
    $file = __DIR__ . '/app/cache/' . $bin . '.yml';
    if (!$fs->exists($file)) {
      $fs->touch($file);
    }
    return $yaml->parse(file_get_contents($file));
  }

  /**
   * Check to see if config file is present and for other requirements.
   *
   * @return true if all requirements pass, false otherwise.
   */
  public function checkRequirements()
  {
    $currentUser = get_current_user();
    $fs = new Filesystem();
    $configFile = '/Users/' . $currentUser . '/.active_collab';

    if (!$fs->exists($configFile)) {
      print "Please create a ~/.active_collab file.\n";
      return false;
    }

    $yaml = new Parser();

    try {
        $file = $yaml->parse(file_get_contents($configFile));
        if (!isset($file['ac_url']) || !$file['ac_url']) {
          print "Please specify a value for ac_url in your config file!\n";
        } else {
          parent::setAPIUrl($file['ac_url']);
        }
        if (!isset($file['ac_token']) || !$file['ac_token']) {
          print "Please specify a value for ac_token in your config file!\n";
        } else {
          parent::setKey($file['ac_token']);
        }
        if (!isset($file['ac_url']) || !isset($file['ac_token']) || !$file['ac_url'] || !$file['ac_token']) {
          return false;
        }
        if (isset($file['projects'])) {
          $this->projects = serialize($file['projects']);
        }
    } catch (ParseException $e) {
        printf("Unable to parse the YAML string: %s", $e->getMessage());
        return false;
    }

    // Create cache directory.
    if (!$fs->exists(__DIR__ . '/app/cache')) {
      $fs->mkdir(__DIR__ . '/app/cache');
    }

    return true;
  }

  /**
   * Get a list of assignees for a task.
   *
   * @param object $ticket
   * @return array
   *         An array of names and user IDs, structured by responsibility.
   *         For example:
   *           array('responsible' => array(10 => 'Some name'), 'assigned' =>
   *            array(12 => 'Someone else', 13 => 'another person');
   *
   */
  public function getAssigneesByTicket($ticket) {
    if (!is_array($ticket)) {
      return false;
    }

    $assignees = $ticket['assignees'];
    $users = array('assigned' => null, 'responsible' => null);

    if (!$assignees) {
      return $users;
    }
    // Loop through assignees and make an array of responsible/assigned.
    foreach ($assignees as $assignee) {
      // Obtain the name for each assignee.
      $user = $this->getUserById($assignee['user_id']);
      if ($assignee['is_owner']) {
        $users['responsible'] = array('id' => $assignee['user_id'], 'name' => $user['first_name'] . ' ' . $user['last_name']);
      } else {
        $users['assigned'][] = array('id' => $assignee['user_id'], 'name' => $user['first_name'] . ' ' . $user['last_name']);
      }
    }
    return $users;
  }

  /**
   * Load a user by user ID.
   *
   * @param int $userId.
   *
   * @return user object or FALSE if not successful.
   */
  public function getUserById($userId) {
    $users = $this->cacheGet('users');
    if (!isset($users[$userId])) {
      // Get all companies in system.
      $companies = $this->api('listPeople');
      $users = array();
      foreach ($companies as $company) {
        // Load the company
        $companyData = $this->getCompanyById($company['id']);
        if (isset($companyData->users) && !empty($companyData->users)) {
          foreach ($companyData->users as $user) {
            $users[$user->id] = (array)$user;
          }
        }
      }
      if ($users) {
        $this->cacheSet($users, 'users');
      }
    }
    if (isset($users[$userId])) {
      return $users[$userId];
    }
  }

  /**
   * Clean up formatting on HTML received from activeCollab.
   *
   * @param string $text
   * @return string
   */
  public function cleanText($text) {
    $text = str_replace('</p>', "\n", $text);
    $text = str_replace('<p>', NULL, $text);
    $text = str_replace("\n \n", "\n", $text);
    $text = str_replace('<ul>', "\n", $text);
    $text = str_replace('<li>', "* ", $text);
    $text = str_replace('</li>', "\n", $text);
    $text = str_replace('</ul>', NULL, $text);
    $text = strip_tags($text);
    return $text;
  }

}
