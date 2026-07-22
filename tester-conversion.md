We're converting Tempest's PHPunit testers to tester classes compatible with this new test runner.

We've already converted two testers manually:

- `\Tempest\Testing\Testers\EventBus\EventBusTester`
- `\Tempest\Testing\Testers\Console\ConsoleTester`

The new testers will all folow the same design:

- A collection of methods that make assertions with one or more dependencies easier
- A trait that can be used in specific test classes to get access to a tester
- The trait is always called `TestsX`, where `X` is the name of the tested component
- The tester class underneath is referenced as a protected property on the trait
- The trait sets up the testers and mocks dependencies. Since this test runner is running in Tempest itself, we need to take care of resetting mocked dependencies when a test is done. 
- Tests for testers should be placed in the same directory as the tester itself
- always run `composer qa` at the end of a finished task

These are the tester classes that still need convertion. Work on them one by one and stop when one is done. 

- [x] `\Tempest\Cache\Testing\CacheTester`
- [x] `\Tempest\Database\Testing\DatabaseTester`
- [x] `\Tempest\Mail\Testing\MailTester`
- [x] `\Tempest\Mail\Testing\AttachmentTester`
- [x] `\Tempest\Framework\Testing\Http\HttpRouterTester` (should become `HttpTester` instead)
- [x] `\Tempest\Process\Testing\ProcessTester`
- [ ] `\Tempest\Storage\Testing\StorageTester`
- [ ] `\Tempest\Framework\Testing\View\ViewTester`
