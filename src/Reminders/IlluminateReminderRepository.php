<?php namespace Cartalyst\Sentinel\Reminders;
/**
 * Part of the Sentinel package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the license.txt file.
 *
 * @package    Sentinel
 * @version    1.0.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2014, Cartalyst LLC
 * @link       http://cartalyst.com
 */

use Carbon\Carbon;
use Cartalyst\Sentinel\Users\UserInterface;
use Cartalyst\Sentinel\Users\UserRepositoryInterface;
use Cartalyst\Support\Traits\EventTrait;
use Cartalyst\Support\Traits\RepositoryTrait;

class IlluminateReminderRepository implements ReminderRepositoryInterface {

	use EventTrait, RepositoryTrait;

	/**
	 * User repository.
	 *
	 * @var \Cartalyst\Sentinel\Users\UserRepositoryInterface
	 */
	protected $users;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	protected $model = 'Cartalyst\Sentinel\Reminders\EloquentReminder';

	/**
	 * Expiration time in seconds.
	 *
	 * @var int
	 */
	protected $expires = 259200;

	/**
	 * Create a new Illuminate reminder repository.
	 *
	 * @param  \Cartalyst\Sentinel\Users\UserRepositoryInterface  $users
	 * @param  string  $model
	 * @param  int  $expires
	 * @return void
	 */
	public function __construct(UserRepositoryInterface $users, $model = null, $expires = null)
	{
		$this->users = $users;

		if (isset($model))
		{
			$this->model = $model;
		}

		if (isset($expires))
		{
			$this->expires = $expires;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function create(UserInterface $user)
	{
		$reminder = $this->createModel();

		$code = $this->generateReminderCode();

		$reminder->fill([
			'code'      => $code,
			'completed' => false,
		]);

		$reminder->user_id = $user->getUserId();

		$reminder->save();

		return $reminder;
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists(UserInterface $user)
	{
		$reminder = $this
			->createModel()
			->newQuery()
			->where('user_id', $user->getUserId())
			->where('completed', false)
			->first();

		return $reminder ?: null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function complete(UserInterface $user, $code, $password)
	{
		$reminder = $this
			->createModel()
			->newQuery()
			->where('user_id', $user->getUserId())
			->where('code', $code)
			->where('completed', false)
			->first();

		if ($reminder === null)
		{
			return false;
		}

		$credentials = compact('password');

		$valid = $this->users->validForUpdate($user, $credentials);

		if ($valid === false)
		{
			return false;
		}

		$this->users->update($user, $credentials);

		$reminder->fill([
			'completed'    => true,
			'completed_at' => Carbon::now(),
		]);

		$reminder->save();

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function removeExpired()
	{
		$expires = Carbon::now()->subSeconds($this->expires);

		return $this
			->createModel()
			->newQuery()
			->where('completed', false)
			->where('created_at', '<', $expires)
			->delete();
	}

	/**
	 * Returns a random string for a reminder code.
	 *
	 * @return string
	 */
	protected function generateReminderCode()
	{
		return str_random(32);
	}

}