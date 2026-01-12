# Test inventory for Teamex

Generated automatically by `bin/generate-test-inventory`. Edit tests to update this list.

## tests/Feature/ApiResources/HasStatesResourceMethodsTest.php

- returns state as resource array
  - Assertions: expect(...)

- accepts user context
  - Assertions: expect(...)

- handles null state
  - Assertions: expect(...)

- returns state for UI display
  - Assertions: expect(...)

- returns null for no state
  - Assertions: expect(...)

- returns next states for UI display
  - Assertions: expect(...)

- accepts user context
  - Assertions: expect(...)

- returns empty array when no transitions available
  - Assertions: expect(...)

## tests/Feature/ApiResources/StateCollectionResourceTest.php

- creates collection for model class
  - Assertions: expect(...)

- creates empty collection for non-stateable model
  - Assertions: expect(...)

- creates collection for next states
  - Assertions: expect(...)

- creates collection with user context
  - Assertions: expect(...)

- supports minimal format
  - Assertions: expect(...)

- supports ui format
  - Assertions: expect(...)

- supports full format
  - Assertions: expect(...)

- sets model context with withModel
  - Assertions: expect(...)

- sets user context with withUser
  - Assertions: expect(...)

- returns states for model from all states
  - Assertions: expect(...)

## tests/Feature/ApiResources/StateResourceTest.php

- creates from state class with basic properties
  - Assertions: expect(...)

- converts to full array
  - Assertions: expect(...)

- converts to minimal array
  - Assertions: expect(...)

- converts to UI array
  - Assertions: expect(...)

- detects current state with model context
  - Assertions: expect(...)

- detects can transition to with model context
  - Assertions: expect(...)

- transforms state to full array
  - Assertions: expect(...)

- transforms state to minimal array
  - Assertions: expect(...)

- transforms state to UI array
  - Assertions: expect(...)

- detects current state with model context
  - Assertions: expect(...)

- calculates can_transition_to with model context
  - Assertions: expect(...)

- works with state instance instead of class
  - Assertions: expect(...)

- accepts user context
  - Assertions: expect(...)

- uses request user when no context user provided
  - Assertions: expect(...)

- supports full method to reset format
  - Assertions: expect(...)

## tests/Feature/ApiResources/StateableResourceTest.php

- includes state data in resource
  - Assertions: expect(...)

- includes next states in resource
  - Assertions: expect(...)

- next states have UI format
  - Assertions: expect(...)

- handles state resource nested format
  - Assertions: expect(...)

- handles minimal state format
  - Assertions: expect(...)

- filters next states by authenticated user
  - Assertions: expect(...)

- handles null state gracefully
  - Assertions: expect(...)

- includes state icon when available
  - Assertions: expect(...)

- available states are in UI format in nested resource
  - Assertions: expect(...)

## tests/Feature/ApiResources/TransitionResourceTest.php

- formats successful transition result
  - Assertions: expect(...)

- formats failed transition result
  - Assertions: expect(...)

- excludes error on success
  - Assertions: expect(...)

- excludes model on failure
  - Assertions: expect(...)

- includes metadata when present
  - Assertions: expect(...)

- excludes metadata when empty
  - Assertions: expect(...)

## tests/Feature/Commands/MakeStateCommandTest.php

- creates a basic state class
  - Assertions: assertExitCode, expect(...)

- creates a state class with custom color
  - Assertions: assertExitCode, expect(...)

- creates a state class with custom icon
  - Assertions: assertExitCode, expect(...)

- creates a default state with attribute
  - Assertions: assertExitCode, expect(...)

- creates a base state class
  - Assertions: assertExitCode, expect(...)

- creates a state class extending a custom base
  - Assertions: assertExitCode, expect(...)

- creates state in same directory when extends has full namespace
  - Assertions: assertExitCode, expect(...)

- handles CamelCase names correctly
  - Assertions: assertExitCode, expect(...)

- creates state in nested namespace
  - Assertions: assertExitCode, expect(...)

- creates all options combined
  - Assertions: assertExitCode, expect(...)

- creates base and multiple state classes with --states option
  - Assertions: assertExitCode, expect(...)

- handles spaces in --states option
  - Assertions: assertExitCode, expect(...)

- creates states and enum with --transitions=enum option
  - Assertions: assertExitCode, expect(...)

- creates enum at custom location with --enum option
  - Assertions: assertExitCode, expect(...)

- includes sync command reminder in generated enum
  - Assertions: assertExitCode, expect(...)

## tests/Feature/Commands/MakeTransitionCommandTest.php

- creates a transition class
  - Assertions: assertExitCode, expect(...)

- appends Transition suffix if not provided
  - Assertions: assertExitCode, expect(...)

- does not duplicate Transition suffix
  - Assertions: assertExitCode, expect(...)

- accepts from and to options
  - Assertions: assertExitCode, expect(...)

- creates transition in nested namespace
  - Assertions: assertExitCode, expect(...)

- contains correct namespace
  - Assertions: assertExitCode, expect(...)

- imports required classes
  - Assertions: assertExitCode, expect(...)

## tests/Feature/Commands/StateFlowAuditCommandTest.php

- fails for non-existent model
  - Assertions: assertExitCode

- fails for model without HasStatesContract
  - Assertions: assertExitCode

- audits Post model successfully
  - Assertions: assertExitCode

- shows default state in audit results
  - Assertions: assertExitCode

- accepts field option
  - Assertions: assertExitCode

- fails for non-existent field
  - Assertions: assertExitCode

- shows audit passed message on success
  - Assertions: assertExitCode

- detects states configuration
  - Assertions: assertExitCode

- expands short model names with App\\Models prefix
  - Assertions: assertExitCode

- accepts fully qualified class names
  - Assertions: assertExitCode

## tests/Feature/Commands/StateFlowListCommandTest.php

- shows usage when no model provided
  - Assertions: assertExitCode

- fails for non-existent model
  - Assertions: assertExitCode

- fails for model without HasStatesContract
  - Assertions: assertExitCode

- lists states for Post model
  - Assertions: assertExitCode

- displays state table with correct headers
  - Assertions: assertExitCode

- shows default state indicator
  - Assertions: assertExitCode

- accepts field option
  - Assertions: assertExitCode

- fails for non-existent field
  - Assertions: assertExitCode

- shows total states count
  - Assertions: assertExitCode

- shows transitions to column
  - Assertions: assertExitCode

- shows permitted roles column
  - Assertions: assertExitCode

## tests/Feature/Commands/SyncEnumCommandTest.php

- creates enum from existing state classes
  - Assertions: assertExitCode, expect(...)

- derives enum class name when not provided
  - Assertions: assertExitCode, expect(...)

- generates enum with all helper methods
  - Assertions: assertExitCode, expect(...)

- includes sync command reminder in generated enum
  - Assertions: assertExitCode, expect(...)

- fails when base state class does not exist
  - Assertions: assertExitCode

- can update existing enum with new states
  - Assertions: assertExitCode, expect(...)

- uses Str::beforeLast for convention-based class resolution
  - Assertions: assertExitCode, expect(...)

- displays informative output
  - Assertions: assertExitCode, expect(...)

- preserves custom canTransitionTo method during sync
  - Assertions: assertExitCode, expect(...)

- preserves complex canTransitionTo with nested arrays
  - Assertions: assertExitCode, expect(...)

- overwrites custom canTransitionTo when force flag is used
  - Assertions: assertExitCode, expect(...)

## tests/Feature/Events/EventDispatchingTest.php

- dispatches StateTransitioning event before transition

- dispatches StateTransitioned event after successful transition

- dispatches both events in correct order

- allows cancelling transition via event

- allows cancelling transition without reason

- does not dispatch StateTransitioned when transition is cancelled

- does not dispatch events when feature is disabled

- can capture event metadata without affecting transition

- includes reason in events when provided

- dispatches TransitionFailed event on invalid transition

## tests/Feature/Events/StateTransitionSubscriberTest.php

- can be instantiated
  - Assertions: expect(...)

- returns correct event mapping
  - Assertions: expect(...)

- logs transitioning event

- does not log when subscriber_enabled is false

- does not log when log_transitioning is false

- logs transitioned event

- does not log when log_transitioned is false

- logs failed event as error

- does not log when log_failed is false

- uses configured log channel

## tests/Feature/Events/StateTransitionedEventTest.php

- can be instantiated
  - Assertions: expect(...)

- can be created with full parameters
  - Assertions: expect(...)

- can be created from TransitionData
  - Assertions: expect(...)

- can be created from TransitionContext
  - Assertions: expect(...)

- provides model information
  - Assertions: expect(...)

- retrieves history id from context
  - Assertions: expect(...)

- returns null when no history id in context
  - Assertions: expect(...)

- generates a summary string
  - Assertions: expect(...)

- converts to array
  - Assertions: expect(...)

- implements StateFlowEvent interface
  - Assertions: expect(...)

## tests/Feature/Events/StateTransitioningEventTest.php

- can be instantiated
  - Assertions: expect(...)

- can be created with full parameters
  - Assertions: expect(...)

- can be created from TransitionData
  - Assertions: expect(...)

- is not cancelled by default
  - Assertions: expect(...)

- can be cancelled
  - Assertions: expect(...)

- can be cancelled without reason
  - Assertions: expect(...)

- provides model information
  - Assertions: expect(...)

- generates a summary string
  - Assertions: expect(...)

- implements StateFlowEvent interface
  - Assertions: expect(...)

## tests/Feature/Events/TransitionFailedEventTest.php

- can be instantiated
  - Assertions: expect(...)

- can be created with full parameters
  - Assertions: expect(...)

- can be created from TransitionData
  - Assertions: expect(...)

- can be created from TransitionContext
  - Assertions: expect(...)

- provides model information
  - Assertions: expect(...)

- checks for exception presence
  - Assertions: expect(...)

- returns exception message
  - Assertions: expect(...)

- returns null for exception message when no exception
  - Assertions: expect(...)

- generates error summary
  - Assertions: expect(...)

- includes error code in summary
  - Assertions: expect(...)

- converts to array
  - Assertions: expect(...)

- implements StateFlowEvent interface
  - Assertions: expect(...)

## tests/Feature/Foundation/ConfigurationTest.php

- merges default configuration
  - Assertions: expect(...)

- has all required config keys
  - Assertions: expect(...)

- has correct default values
  - Assertions: expect(...)

- config can be overridden
  - Assertions: expect(...)

- has all event toggles
  - Assertions: expect(...)

- has all feature flags
  - Assertions: expect(...)

## tests/Feature/Foundation/ServiceProviderTest.php

- registers the service provider
  - Assertions: expect(...)

- binds PermissionChecker contract to container
  - Assertions: expect(...)

- registers the facade accessor
  - Assertions: expect(...)

- facade resolves to StateFlow class
  - Assertions: expect(...)

## tests/Feature/Foundation/StateFlowCoreClassTest.php

- can disable migrations
  - Assertions: expect(...)

- can customize history model
  - Assertions: expect(...)

- can register states for a base class
  - Assertions: expect(...)

- returns empty array for unregistered base class
  - Assertions: expect(...)

- can register custom transitions
  - Assertions: expect(...)

- returns null for unregistered transitions
  - Assertions: expect(...)

- can check feature flags
  - Assertions: expect(...)

- correctly determines if history is recorded
  - Assertions: expect(...)

- correctly determines if permissions are checked
  - Assertions: expect(...)

- can reset all static configuration
  - Assertions: expect(...)

## tests/Feature/History/HasStateHistoryTest.php

- can get state history relationship
  - Assertions: expect(...)

- can get state history for field
  - Assertions: expect(...)

- can get last transition
  - Assertions: expect(...)

- can get first transition
  - Assertions: expect(...)

- can get recent transitions
  - Assertions: expect(...)

- can get transitions by performer
  - Assertions: expect(...)

- can get transitions to a specific state
  - Assertions: expect(...)

- can get transitions from a specific state
  - Assertions: expect(...)

- can count transitions
  - Assertions: expect(...)

- can check if model was ever in state
  - Assertions: expect(...)

- can check if model transitioned from one state to another
  - Assertions: expect(...)

- can get state timeline
  - Assertions: expect(...)

- can get unique states
  - Assertions: expect(...)

- can get transition counts by state
  - Assertions: expect(...)

- can check for automated transitions
  - Assertions: expect(...)

- can clear state history
  - Assertions: expect(...)

- returns null for time in current state when no history
  - Assertions: expect(...)

- returns time in current state from last transition
  - Assertions: expect(...)

- returns current state entered at timestamp
  - Assertions: expect(...)

- can get duration between states
  - Assertions: expect(...)

- returns null for duration when states not found
  - Assertions: expect(...)

- can get duration from state to now
  - Assertions: expect(...)

## tests/Feature/History/RecordStateTransitionTest.php

- records transition from TransitionContext
  - Assertions: expect(...)

- records transition with performer
  - Assertions: expect(...)

- records transition from StateHistoryData
  - Assertions: expect(...)

- records transition with raw parameters
  - Assertions: expect(...)

- records transition from TransitionData
  - Assertions: expect(...)

- returns null when history is disabled
  - Assertions: expect(...)

- returns null for raw when history is disabled
  - Assertions: expect(...)

- dispatches event when configured

- does not dispatch event by default

- creates from TransitionContext
  - Assertions: expect(...)

- creates from TransitionData
  - Assertions: expect(...)

- converts to array
  - Assertions: expect(...)

- creates model from DTO
  - Assertions: expect(...)

- creates immutable copy with performer
  - Assertions: expect(...)

- creates immutable copy with metadata
  - Assertions: expect(...)

- creates immutable copy with reason
  - Assertions: expect(...)

## tests/Feature/History/StateHistoryModelTest.php

- can create a state history entry
  - Assertions: expect(...)

- can retrieve model through morph relation
  - Assertions: expect(...)

- can retrieve performer through morph relation
  - Assertions: expect(...)

- can filter by model using forModel scope
  - Assertions: expect(...)

- can filter by from and to state
  - Assertions: expect(...)

- can filter by field
  - Assertions: expect(...)

- can filter by performer
  - Assertions: expect(...)

- can filter automated transitions
  - Assertions: expect(...)

- can order by latest and oldest
  - Assertions: expect(...)

- can filter by date range
  - Assertions: expect(...)

- returns summary string
  - Assertions: expect(...)

- returns summary with performer name
  - Assertions: expect(...)

- checks if automated
  - Assertions: expect(...)

- checks wasPerformedBy
  - Assertions: expect(...)

- can get and check metadata
  - Assertions: expect(...)

- converts to summary array
  - Assertions: expect(...)

## tests/Feature/Queries/StateQueryMacrosTest.php

- can order by custom state priority using string names
  - Assertions: expect(...)

- can order by custom state priority using class names
  - Assertions: expect(...)

- places unlisted states at the end of order
  - Assertions: expect(...)

- can chain with other query methods
  - Assertions: expect(...)

- filters states with higher priority
  - Assertions: expect(...)

- filters using class names
  - Assertions: expect(...)

- returns empty when target is highest priority
  - Assertions: expect(...)

- returns empty when state not in priority order
  - Assertions: expect(...)

- includes transition count in results
  - Assertions: expect(...)

- can order by transition count
  - Assertions: expect(...)

- can filter models with transitions
  - Assertions: expect(...)

- includes last transition date in results
  - Assertions: expect(...)

- can order by last transition date
  - Assertions: expect(...)

- can chain multiple macros with scopes
  - Assertions: expect(...)

## tests/Feature/Queries/StateQueryScopesTest.php

- can filter by state with whereState
  - Assertions: expect(...)

- can filter by state using class name
  - Assertions: expect(...)

- can exclude state with whereStateNot
  - Assertions: expect(...)

- can filter by state in list
  - Assertions: expect(...)

- can filter using class names
  - Assertions: expect(...)

- can chain with other conditions
  - Assertions: expect(...)

- can exclude multiple states
  - Assertions: expect(...)

- can exclude using class names
  - Assertions: expect(...)

- filters by active (non-final) states
  - Assertions: expect(...)

- filters by final (terminal) states
  - Assertions: expect(...)

- finds states that can transition to a target
  - Assertions: expect(...)

- finds states that can transition to target using class
  - Assertions: expect(...)

- returns empty when no transitions possible
  - Assertions: expect(...)

- filters by initial/default state
  - Assertions: expect(...)

- filters by non-initial states
  - Assertions: expect(...)

- can filter by models that were ever in a state
  - Assertions: expect(...)

- can filter by transition from-to path
  - Assertions: expect(...)

- can filter by state changed after date
  - Assertions: expect(...)

- can filter by state changed by specific user
  - Assertions: expect(...)

- can filter by minimum transition count
  - Assertions: expect(...)

- can chain multiple state scopes
  - Assertions: expect(...)

- can chain state scopes with order
  - Assertions: expect(...)

## tests/Feature/Queries/StateStatisticsTest.php

- can count by state
  - Assertions: expect(...)

- returns empty collection for empty table
  - Assertions: expect(...)

- can get percentage by state
  - Assertions: expect(...)

- returns empty collection for empty table
  - Assertions: expect(...)

- can get most common transitions
  - Assertions: expect(...)

- respects limit parameter
  - Assertions: expect(...)

- returns empty for no transitions
  - Assertions: expect(...)

- can count transitions for a model
  - Assertions: expect(...)

- returns zero for no transitions
  - Assertions: expect(...)

- returns time since last transition
  - Assertions: expect(...)

- returns null for no transitions
  - Assertions: expect(...)

- returns models ordered by transition count
  - Assertions: expect(...)

- respects limit parameter
  - Assertions: expect(...)

- finds models stuck in a state
  - Assertions: expect(...)

- excludes recently transitioned models
  - Assertions: expect(...)

- groups transitions by day
  - Assertions: expect(...)

## tests/Feature/StatesAndDTOs/AttributesTest.php

- returns correct name from static property
  - Assertions: expect(...)

- returns correct title from StateMetadata attribute
  - Assertions: expect(...)

- returns correct color from StateMetadata attribute
  - Assertions: expect(...)

- returns correct icon from StateMetadata attribute
  - Assertions: expect(...)

- returns permitted roles from StatePermission attribute
  - Assertions: expect(...)

- returns allowed transitions from AllowTransition attributes
  - Assertions: expect(...)

- detects default state from DefaultState attribute
  - Assertions: expect(...)

- checks transition is allowed via attributes
  - Assertions: expect(...)

- falls back to defaults when attributes feature disabled
  - Assertions: expect(...)

- returns empty transitions when attributes disabled and no constants
  - Assertions: expect(...)

## tests/Feature/StatesAndDTOs/DTOsTest.php

- creates from state class
  - Assertions: expect(...)

- creates with constructor
  - Assertions: expect(...)

- converts to array with all fields
  - Assertions: expect(...)

- converts to resource array with UI fields only
  - Assertions: expect(...)

- includes allowed transitions in array
  - Assertions: expect(...)

- includes permitted roles in array
  - Assertions: expect(...)

- creates via constructor
  - Assertions: expect(...)

- creates via make method
  - Assertions: expect(...)

- stores metadata
  - Assertions: expect(...)

- creates success result
  - Assertions: expect(...)

- creates failure result
  - Assertions: expect(...)

- includes metadata in success result
  - Assertions: expect(...)

- includes metadata in failure result
  - Assertions: expect(...)

## tests/Feature/StatesAndDTOs/ExceptionsTest.php

- creates unknown state exception
  - Assertions: expect(...)

- creates invalid value exception for object
  - Assertions: expect(...)

- creates invalid value exception for scalar
  - Assertions: expect(...)

- creates not in allowed transitions exception
  - Assertions: expect(...)

- creates insufficient permission exception
  - Assertions: expect(...)

- creates missing configuration exception
  - Assertions: expect(...)

- creates invalid state class exception
  - Assertions: expect(...)

## tests/Feature/StatesAndDTOs/StateCasterTest.php

- casts string to state instance on get
  - Assertions: expect(...)

- returns null for null value
  - Assertions: expect(...)

- throws on unknown state
  - Assertions: expect(...)

- resolves state by class name
  - Assertions: expect(...)

- casts state instance to string on set
  - Assertions: expect(...)

- validates state string on set
  - Assertions: expect(...)

- returns null for null value on set
  - Assertions: expect(...)

- throws on unknown state string on set
  - Assertions: expect(...)

- throws on invalid value type
  - Assertions: expect(...)

- creates caster via castUsing
  - Assertions: expect(...)

## tests/Feature/StatesAndDTOs/StateClassTest.php

- returns correct name from constant
  - Assertions: expect(...)

- returns correct title from constant
  - Assertions: expect(...)

- returns correct color from constant
  - Assertions: expect(...)

- returns icon when defined
  - Assertions: expect(...)

- returns allowed transitions from constant
  - Assertions: expect(...)

- returns permitted roles from constant
  - Assertions: expect(...)

- detects default state from constant
  - Assertions: expect(...)

- checks if transition is allowed
  - Assertions: expect(...)

- checks if transition to instance is allowed
  - Assertions: expect(...)

- returns empty array for terminal states
  - Assertions: expect(...)

- allows bidirectional transitions when configured
  - Assertions: expect(...)

- creates state instance with model
  - Assertions: expect(...)

- returns morph class as state name
  - Assertions: expect(...)

- converts to resource array
  - Assertions: expect(...)

- serializes to JSON correctly
  - Assertions: expect(...)

- converts to string correctly
  - Assertions: expect(...)

- checks equality with same state class
  - Assertions: expect(...)

- checks equality with state name string
  - Assertions: expect(...)

- checks equality with class name string
  - Assertions: expect(...)

- returns false for different state classes
  - Assertions: expect(...)

## tests/Feature/Testing/AssertStateTest.php

- passes when model is in expected state
  - Assertions: assertModelInState

- fails when model is not in expected state
  - Assertions: assertModelInState, expect(...)

- accepts optional field parameter
  - Assertions: assertModelInState

- passes when model is not in unexpected state
  - Assertions: assertModelNotInState

- fails when model is in unexpected state
  - Assertions: assertModelNotInState, expect(...)

- passes for valid transition
  - Assertions: assertCanTransitionTo

- fails for invalid transition
  - Assertions: assertCanTransitionTo, expect(...)

- passes for invalid transition
  - Assertions: assertCannotTransitionTo

- fails for valid transition
  - Assertions: assertCannotTransitionTo, expect(...)

- passes for successful result
  - Assertions: assertTransitionSucceeded

- fails for failed result
  - Assertions: assertTransitionSucceeded, expect(...)

- passes for failed result
  - Assertions: assertTransitionFailed

- fails for successful result
  - Assertions: assertTransitionFailed, expect(...)

- can check for expected error message
  - Assertions: assertTransitionFailed, expect(...)

- passes when all expected transitions are allowed
  - Assertions: assertHasAllowedTransitions

- fails when expected transition not allowed
  - Assertions: assertHasAllowedTransitions, expect(...)

- passes when transitions match exactly
  - Assertions: assertAllowedTransitionsExactly

- fails when transitions do not match
  - Assertions: assertAllowedTransitionsExactly, expect(...)

- handles multiple transitions
  - Assertions: assertAllowedTransitionsExactly

- passes for terminal state
  - Assertions: assertNoAllowedTransitions

- fails for non-terminal state
  - Assertions: assertNoAllowedTransitions, expect(...)

- passes for terminal state
  - Assertions: assertInTerminalState

- fails for non-terminal state
  - Assertions: assertInTerminalState, expect(...)

- passes for default state
  - Assertions: assertInInitialState

- fails for non-default state
  - Assertions: assertInInitialState, expect(...)

## tests/Feature/Testing/PestExpectationsTest.php

- passes when model is in expected state
  - Assertions: expect(...)

- fails when model is not in expected state
  - Assertions: expect(...)

- accepts optional field parameter
  - Assertions: expect(...)

- passes when model is not in unexpected state
  - Assertions: expect(...)

- fails when model is in unexpected state
  - Assertions: expect(...)

- passes for valid transition
  - Assertions: expect(...)

- fails for invalid transition
  - Assertions: expect(...)

- passes for invalid transition
  - Assertions: expect(...)

- fails for valid transition
  - Assertions: expect(...)

- passes when all expected transitions are allowed
  - Assertions: expect(...)

- fails when expected transition not allowed
  - Assertions: expect(...)

- passes when transitions match exactly
  - Assertions: expect(...)

- handles multiple transitions
  - Assertions: expect(...)

- fails when transitions do not match
  - Assertions: expect(...)

- passes for terminal state
  - Assertions: expect(...)

- fails for non-terminal state
  - Assertions: expect(...)

- passes for default state
  - Assertions: expect(...)

- fails for non-default state
  - Assertions: expect(...)

- passes for successful result
  - Assertions: expect(...)

- fails for failed result
  - Assertions: expect(...)

- passes for failed result
  - Assertions: expect(...)

- fails for successful result
  - Assertions: expect(...)

- can check for expected error message
  - Assertions: expect(...)

- passes when transitioned to expected state
  - Assertions: expect(...)

- fails when transitioned to different state
  - Assertions: expect(...)

- passes when transitioned from expected state
  - Assertions: expect(...)

- fails when transitioned from different state
  - Assertions: expect(...)

- supports chaining model expectations
  - Assertions: expect(...)

- supports chaining result expectations
  - Assertions: expect(...)

## tests/Feature/Testing/StateFlowFakeTest.php

- returns a StateFlowFake instance
  - Assertions: expect(...)

- can be instantiated directly
  - Assertions: expect(...)

- records a transition
  - Assertions: expect(...)

- records multiple transitions
  - Assertions: expect(...)

- records transition with success flag
  - Assertions: expect(...)

- records transition with failure flag
  - Assertions: expect(...)

- records model type and id
  - Assertions: expect(...)

- passes when transition was recorded
  - Assertions: assertTransitioned

- fails when transition was not recorded
  - Assertions: assertTransitioned, expect(...)

- returns self for chaining
  - Assertions: assertTransitioned, expect(...)

- passes when no transitions recorded
  - Assertions: assertNotTransitioned

- passes when different transition recorded
  - Assertions: assertNotTransitioned

- fails when transition was recorded
  - Assertions: assertNotTransitioned, expect(...)

- can filter by from state
  - Assertions: assertNotTransitioned, expect(...)

- can filter by to state
  - Assertions: assertNotTransitioned, expect(...)

- passes with correct count
  - Assertions: assertTransitionCount

- fails with incorrect count
  - Assertions: assertTransitionCount, expect(...)

- filters by model when provided
  - Assertions: assertTransitionCount

- passes when no transitions recorded
  - Assertions: assertNoTransitions

- fails when transitions recorded
  - Assertions: assertNoTransitions, expect(...)

- passes for specific model with no transitions
  - Assertions: assertNoTransitions

- passes for successful transition
  - Assertions: assertTransitionSucceeded

- fails for failed transition
  - Assertions: assertTransitionSucceeded, expect(...)

- passes for failed transition
  - Assertions: assertTransitionFailed

- fails for successful transition
  - Assertions: assertTransitionFailed, expect(...)

- checks error message when provided
  - Assertions: assertTransitionFailed, expect(...)

- marks transition as prevented
  - Assertions: expect(...)

- returns self for chaining
  - Assertions: expect(...)

- marks all transitions as prevented
  - Assertions: expect(...)

- stores forced result
  - Assertions: expect(...)

- returns self for chaining
  - Assertions: expect(...)

- returns transitions for specific model
  - Assertions: expect(...)

- clears all recorded transitions
  - Assertions: expect(...)

- returns self for chaining
  - Assertions: expect(...)

## tests/Feature/Validation/FormRequestIntegrationTest.php

- validates state is valid and transition is allowed
  - Assertions: expect(...)

- fails first on state validation if invalid state
  - Assertions: expect(...)

- fails on transition if state is valid but transition is not
  - Assertions: expect(...)

- validates state along with other fields
  - Assertions: expect(...)

- validates both state and reason with errors
  - Assertions: expect(...)

- validates with user permissions
  - Assertions: expect(...)

- fails when user lacks permission
  - Assertions: expect(...)

- stops validation on first failure with bail
  - Assertions: expect(...)

- can use custom validation messages
  - Assertions: expect(...)

- validates with only allowed states
  - Assertions: expect(...)

- fails for valid state that is excluded
  - Assertions: expect(...)

- fails by default when transitioning to same state
  - Assertions: expect(...)

- passes when same state is allowed
  - Assertions: expect(...)

## tests/Feature/Validation/StateRuleTest.php

- passes for valid state
  - Assertions: expect(...)

- passes for all registered states
  - Assertions: expect(...)

- fails for invalid state
  - Assertions: expect(...)

- fails for empty string
  - Assertions: expect(...)

- fails for non-string value
  - Assertions: expect(...)

- includes valid states in error message
  - Assertions: expect(...)

- fails for null when not nullable
  - Assertions: expect(...)

- passes for null when nullable
  - Assertions: expect(...)

- passes when state is in only list
  - Assertions: expect(...)

- fails when state is not in only list
  - Assertions: expect(...)

- passes when state is not in except list
  - Assertions: expect(...)

- fails when state is in except list
  - Assertions: expect(...)

- can create rule using static for method
  - Assertions: expect(...)

- can chain methods fluently
  - Assertions: expect(...)

- passes for valid state
  - Assertions: expect(...)

- fails for invalid state
  - Assertions: expect(...)

- filters with only
  - Assertions: expect(...)

- filters with except
  - Assertions: expect(...)

- can hide valid states from error message
  - Assertions: expect(...)

## tests/Feature/Validation/TransitionRuleTest.php

- passes for valid transition
  - Assertions: expect(...)

- fails for invalid transition
  - Assertions: expect(...)

- fails for null value
  - Assertions: expect(...)

- fails for non-string value
  - Assertions: expect(...)

- fails when transitioning to same state by default
  - Assertions: expect(...)

- passes when transitioning to same state with allowSameState
  - Assertions: expect(...)

- can specify a custom field
  - Assertions: expect(...)

- validates with permission checking enabled
  - Assertions: expect(...)

- fails when user lacks permission
  - Assertions: expect(...)

- fails when no user is authenticated and checking permissions
  - Assertions: expect(...)

- can use custom error message
  - Assertions: expect(...)

- can create rule using static for method
  - Assertions: expect(...)

- can chain methods fluently
  - Assertions: expect(...)

- validates multi-step transition path
  - Assertions: expect(...)

- passes for valid transition
  - Assertions: expect(...)

- fails for invalid transition
  - Assertions: expect(...)

- can allow same state
  - Assertions: expect(...)

- can check permissions
  - Assertions: expect(...)
