# OmniBuy Individual Test Cases

## Testing Information

Project: OmniBuy
Testing Scope: Individual assigned use cases
Testing Framework: PHPUnit
Testing Environment: Docker, PHP, MySQL

## UC-07: Choose Delivery Method
### TC-07-01: Select Shipping as the Delivery Method

**Use Case:** UC-07

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that a buyer can select Shipping when
the seller offers shipping for a listing.

**Preconditions:**
- OmniBuy is running in Docker.
- A listing supports shipping.
- The buyer can access the supported purchase workflow.

**Test Data:**
- Listing supporting Shipping
- Buyer account or supported buyer session

**Test Steps:**
1. Open a listing that supports shipping.
2. Begin the supported purchase process.
3. Examine the available delivery methods.
4. Select Shipping.
5. Continue to the next applicable purchase step.

**Expected Result:**
- Shipping should be available for selection.
- The buyer should be able to select Shipping.
- The selected delivery method should be accepted.
- No unsupported delivery method should be substituted.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-07-02: Select Meetup as the Delivery Method

**Use Case:** UC-07

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that a buyer can select Meetup when
the seller offers meetup for a listing.

**Preconditions:**
- OmniBuy is running in Docker.
- A listing supports meetup.
- The buyer can access the supported purchase workflow.

**Test Data:**
- Listing supporting Meetup
- Buyer account or supported buyer session

**Test Steps:**
1. Open a listing that supports meetup.
2. Begin the supported purchase process.
3. Examine the available delivery methods.
4. Select Meetup.
5. Continue to the next applicable purchase step.

**Expected Result:**
- Meetup should be available for selection.
- The buyer should be able to select Meetup.
- The selected delivery method should be accepted.
- No unsupported delivery method should be substituted.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-07-03: Display Both Supported Delivery Methods

**Use Case:** UC-07

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that both Shipping and Meetup are available
when a seller supports both methods for a listing.

**Preconditions:**
- OmniBuy is running in Docker.
- The application supports listings offering both methods.
- A controlled listing offers both Shipping and Meetup.

**Test Data:**
- Listing supporting both delivery methods
- Buyer account or supported buyer session

**Test Steps:**
1. Open a listing that supports both delivery methods.
2. Begin the supported purchase process.
3. Examine the delivery method selection interface.
4. Verify that Shipping and Meetup are available.
5. Select each method in separate test attempts.

**Expected Result:**
- Both supported delivery methods should be displayed.
- The buyer should be able to select either supported method.
- The chosen method should be accepted correctly.

**Actual Result:**
Not yet recorded.

**Status:** Not Run - Requires confirmation of dual-method support

**Related Jira Defect:** None


### TC-07-04: Reject Unsupported Delivery Method

**Use Case:** UC-07

**Test Type:** Automated Integration / Negative Testing

**Objective:**
Verify that buyers cannot select a delivery method
the seller does not offer.

**Preconditions:**
- An isolated test database is available.
- A listing supports only Meetup.

**Test Data:**
- Meetup-only listing
- Attempted delivery method: Shipping

**Test Steps:**
1. Open a listing that supports only Meetup.
2. Begin the supported purchase process.
3. Verify that Shipping is not offered as a valid option.
4. Attempt to submit Shipping through the applicable request.
5. Examine the application's response.
6. Check whether an invalid selection was stored.

**Expected Result:**
- Unsupported delivery methods should not be available
  through the normal interface.
- A manually submitted unsupported value should be rejected.
- No invalid delivery selection should be stored.
- The listing's supported delivery method should remain unchanged.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-07-05: Preserve Selected Delivery Method During Purchase

**Use Case:** UC-07

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that the buyer's selected delivery method
is preserved during the supported purchase process.

**Preconditions:**
- OmniBuy is running in Docker.
- The purchase workflow supports delivery selection.
- A listing with a supported delivery method exists.

**Test Data:**
- Valid buyer session
- Listing supporting Shipping or Meetup
- Selected delivery method

**Test Steps:**
1. Begin a purchase for an eligible listing.
2. Select a supported delivery method.
3. Continue through the applicable purchase steps.
4. Examine the saved transaction information.
5. Verify the stored delivery method.

**Expected Result:**
- The selected delivery method should remain consistent
  throughout the supported transaction workflow.
- The saved selection should match the buyer's choice.
- No unsupported delivery method should be stored.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None
## UC-11: View Listing Analytics
### TC-11-01: View Analytics for a Seller-Owned Listing

**Use Case:** UC-11

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that a seller can access analytics for
a listing they own.

**Preconditions:**
- OmniBuy is running in Docker.
- A seller account has an existing listing.
- Listing analytics functionality is implemented.

**Test Data:**
- Seller account
- Known seller-owned listing ID
- Available listing analytics

**Test Steps:**
1. Log in as the seller.
2. Navigate to the seller's listing management area.
3. Select a listing owned by the seller.
4. Open the listing's analytics.
5. Examine the displayed analytics information.

**Expected Result:**
- The seller should be able to access their listing's analytics.
- Available metrics should be displayed correctly.
- The analytics should correspond to the selected listing.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-11-02: Verify Analytics Metric Accuracy

**Use Case:** UC-11

**Test Type:** Automated Integration

**Objective:**
Verify that implemented analytics metrics match
the corresponding stored test data.

**Preconditions:**
- An isolated test database is available.
- The supported analytics metrics are defined.
- Controlled listing analytics data exists.

**Test Data:**
- Known listing ID
- Predetermined values for each supported metric
- Database records associated with the listing

**Test Steps:**
1. Prepare a listing with known analytics data.
2. Retrieve the relevant source records from the database.
3. Calculate the expected values for supported metrics.
4. Access the listing's analytics.
5. Compare the displayed values with the expected results.

**Expected Result:**
- Each implemented metric should match its expected value.
- Values should be calculated from the correct source data.
- No unrelated listing data should affect the results.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-11-03: Verify Analytics Belong to the Correct Listing

**Use Case:** UC-11

**Test Type:** Automated Integration

**Objective:**
Verify that analytics from one listing are not
incorrectly displayed for another listing.

**Preconditions:**
- An isolated test database is available.
- One seller owns multiple listings.
- Each listing has different controlled analytics data.

**Test Data:**
- Listing A with known analytics values
- Listing B with different analytics values
- Both listings belong to the same seller

**Test Steps:**
1. Prepare controlled analytics records for Listing A.
2. Prepare different analytics records for Listing B.
3. Open analytics for Listing A.
4. Record the displayed values.
5. Open analytics for Listing B.
6. Compare both sets of displayed values with their
   corresponding database records.

**Expected Result:**
- Listing A should display only its own analytics.
- Listing B should display only its own analytics.
- Metrics should not be mixed between listings.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-11-04: Handle Missing or Zero-Valued Analytics

**Use Case:** UC-11

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that listings with no recorded analytics
display appropriate values without errors.

**Preconditions:**
- OmniBuy is running in Docker.
- Listing analytics functionality is implemented.
- A listing with no analytics activity exists.

**Test Data:**
- Known listing with no recorded analytics activity
- Missing or zero-valued supported metrics

**Test Steps:**
1. Log in as the listing owner.
2. Open analytics for a listing with no recorded activity.
3. Examine each available metric.
4. Verify the displayed values.
5. Check for calculation or database errors.

**Expected Result:**
- Zero-valued metrics should display appropriately.
- Missing analytics should not cause the page to fail.
- The interface should not display misleading statistics.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-11-05: Prevent Unauthorized Analytics Access

**Use Case:** UC-11

**Test Type:** Automated Integration / Negative Testing

**Objective:**
Verify that sellers cannot access restricted analytics
belonging to another seller.

**Preconditions:**
- An isolated test database is available.
- Two separate seller accounts exist.
- Seller A owns a listing with analytics data.

**Test Data:**
- Seller A: Listing owner
- Seller B: Unauthorized account
- Known listing ID belonging to Seller A

**Test Steps:**
1. Authenticate as Seller B.
2. Attempt to access analytics for Seller A's listing.
3. Examine the application's response.
4. Verify that restricted analytics are not returned.
5. Confirm that Seller A can still access their own analytics.

**Expected Result:**
- Seller B should not be allowed to access restricted analytics.
- Unauthorized requests should be rejected.
- Restricted analytics values should not be disclosed.
- The listing owner should retain authorized access.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None
## UC-14: Mark Listing as Sold
### TC-14-01: Mark an Active Listing as Sold

**Use Case:** UC-14

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that a seller can mark their own active listing as sold.

**Preconditions:**
- OmniBuy is running in Docker.
- A seller account has an active listing.
- The seller is logged in.

**Test Data:**
- Seller account with an active listing
- Listing status: Active

**Test Steps:**
1. Log in as the seller.
2. Navigate to the seller's active listings.
3. Select an active listing.
4. Choose the option to mark the listing as sold.
5. Complete the action.
6. Examine the resulting listing information.

**Expected Result:**
- The seller should be able to complete the action.
- The listing should be marked as sold.
- The interface should reflect the updated listing status.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-14-02: Verify Listing Status in Database

**Use Case:** UC-14

**Test Type:** Automated Integration

**Objective:**
Verify that marking a listing as sold updates the
correct listing record in the database.

**Preconditions:**
- An isolated test database is available.
- An active listing belongs to a known seller.

**Test Data:**
- Known listing ID
- Initial listing status: Active
- Expected final listing status: Sold

**Test Steps:**
1. Record the listing's initial status.
2. Mark the listing as sold using the application.
3. Retrieve the listing record from the database.
4. Compare the stored status with the expected value.

**Expected Result:**
- The correct listing should have its status updated to Sold.
- The listing ID and seller ownership should remain unchanged.
- Unrelated listing records should not be modified.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-14-03: Exclude Sold Listings From Active Search Results

**Use Case:** UC-14

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that sold listings no longer appear in active
marketplace search results.

**Preconditions:**
- OmniBuy is running in Docker.
- A known listing has been marked as sold.

**Test Data:**
- Known sold listing ID
- Active listings that remain available

**Test Steps:**
1. Mark a known active listing as sold.
2. Open Browse All Listings.
3. Search for the sold listing by its item name.
4. Examine the returned results.
5. Compare the results against active listing records.

**Expected Result:**
- The sold listing should not appear in active marketplace results.
- Other eligible active listings should still appear.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-14-04: Verify Sold Listing History

**Use Case:** UC-14

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that sold listings remain accessible through
the seller's sold-listing history.

**Preconditions:**
- OmniBuy is running in Docker.
- The seller has a listing marked as sold.
- The seller's sold-listing history is implemented.

**Test Data:**
- Seller account
- Known sold listing

**Test Steps:**
1. Log in as the seller.
2. Navigate to the seller's listing management area.
3. Open the sold-listing history.
4. Locate the previously sold listing.
5. Examine its status and listing information.

**Expected Result:**
- The sold listing should remain accessible to its seller.
- The listing should be identified as sold.
- Its listing information should remain associated with
  the correct listing record.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-14-05: Prevent Unauthorized Listing Status Changes

**Use Case:** UC-14

**Test Type:** Automated Integration / Negative Testing

**Objective:**
Verify that a seller cannot mark another seller's
listing as sold.

**Preconditions:**
- An isolated test database is available.
- Two separate seller accounts exist.
- Seller A owns an active listing.

**Test Data:**
- Seller A: Listing owner
- Seller B: Unauthorized account
- Known listing ID belonging to Seller A

**Test Steps:**
1. Log in or authenticate as Seller B.
2. Attempt to mark Seller A's listing as sold.
3. Examine the application's response.
4. Retrieve the listing status from the database.

**Expected Result:**
- The unauthorized action should be rejected.
- The listing should remain active.
- Seller B should not gain permission to modify the listing.
- No unrelated database records should change.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-14-06: Handle Repeated or Invalid Status Changes

**Use Case:** UC-14

**Test Type:** Automated Integration / Negative Testing

**Objective:**
Verify that repeated or invalid attempts to mark a
listing as sold do not create incorrect status changes.

**Preconditions:**
- An isolated test database is available.
- A seller-owned listing is already marked as sold.

**Test Data:**
- Known sold listing
- Repeated Mark as Sold request
- Listing in another ineligible status, if supported

**Test Steps:**
1. Log in as the listing owner.
2. Open a listing already marked as sold.
3. Attempt to mark the listing as sold again.
4. Examine the application's response.
5. Verify the listing's stored status.
6. Repeat with another ineligible status, if applicable.

**Expected Result:**
- The application should handle the repeated request safely.
- The listing should remain in the correct status.
- No invalid status transition should occur.
- No duplicate or unintended database changes should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None
## UC-15: Renew Old Listing
### TC-15-01: Renew an Eligible Listing

**Use Case:** UC-15

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that a seller can successfully renew an eligible listing.

**Preconditions:**
- OmniBuy is running in Docker.
- A seller account has a listing eligible for renewal.
- The seller is logged in.

**Test Data:**
- Known eligible listing
- Listing owner account

**Test Steps:**
1. Log in as the seller.
2. Navigate to the seller's listings.
3. Locate a listing eligible for renewal.
4. Select the Renew option.
5. Complete the renewal process.
6. Examine the listing's updated information.

**Expected Result:**
- The seller should be able to renew the eligible listing.
- The application should recognize the renewal.
- The listing should remain associated with its owner.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-15-02: Verify Renewal Timestamp Update

**Use Case:** UC-15

**Test Type:** Automated Integration

**Objective:**
Verify that renewing a listing updates the appropriate
renewal timestamp without changing unrelated listing data.

**Preconditions:**
- An isolated test database is available.
- A known listing is eligible for renewal.

**Test Data:**
- Known listing ID
- Initial renewal timestamp
- Renewal action timestamp

**Test Steps:**
1. Retrieve the listing's initial timestamp information.
2. Renew the listing through the application.
3. Retrieve the updated database record.
4. Compare the timestamp values.
5. Verify unrelated listing fields remain unchanged.

**Expected Result:**
- The renewal timestamp should update according to
  the application's renewal rules.
- The correct listing should be updated.
- Unrelated listing data should remain unchanged.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-15-03: Verify Renewal Affects Listing Sorting

**Use Case:** UC-15, UC-24

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that renewing a listing affects marketplace sorting
according to the team's agreed renewal-ordering rules.

**Preconditions:**
- OmniBuy is running in Docker.
- The team's renewal-ordering behavior is defined.
- Active listings with known timestamps exist.
- A listing eligible for renewal exists.

**Test Data:**
- Known listing eligible for renewal
- Active listings with different creation dates
- Sorting option: Newest

**Test Steps:**
1. Open Browse All Listings.
2. Select Newest sorting.
3. Record the eligible listing's original position.
4. Renew the listing as its owner.
5. Return to Browse All Listings.
6. Select Newest sorting.
7. Examine the renewed listing's position.

**Expected Result:**
- The renewed listing should appear in the position
  specified by the agreed renewal-ordering rules.
- Other listings should retain the correct relative order.
- No unrelated listing records should change.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-15-04: Prevent Unauthorized Listing Renewal

**Use Case:** UC-15

**Test Type:** Automated Integration / Negative Testing

**Objective:**
Verify that a seller cannot renew a listing
belonging to another seller.

**Preconditions:**
- An isolated test database is available.
- Two separate seller accounts exist.
- Seller A owns a listing eligible for renewal.

**Test Data:**
- Seller A: Listing owner
- Seller B: Unauthorized account
- Known listing ID belonging to Seller A

**Test Steps:**
1. Authenticate as Seller B.
2. Attempt to renew Seller A's listing.
3. Examine the application's response.
4. Retrieve the listing's renewal timestamp.
5. Compare the record to its original state.

**Expected Result:**
- The unauthorized renewal should be rejected.
- The listing's renewal timestamp should remain unchanged.
- Seller B should not gain access to the owner's controls.
- No unrelated database records should change.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-15-05: Prevent Renewal of Ineligible Listings

**Use Case:** UC-15

**Test Type:** Automated Integration / Negative Testing

**Objective:**
Verify that listings that do not meet renewal
requirements cannot be renewed.

**Preconditions:**
- An isolated test database is available.
- The team's renewal eligibility rules are defined.
- At least one ineligible listing exists.

**Test Data:**
- Listing that does not meet renewal eligibility requirements
- Listing owner account

**Test Steps:**
1. Log in as the listing owner.
2. Identify a listing that is not eligible for renewal.
3. Attempt to renew the listing.
4. Examine the application's response.
5. Retrieve the listing's database record.

**Expected Result:**
- The renewal should be rejected.
- The listing should remain in its original state.
- Renewal timestamps should not be incorrectly updated.
- No unrelated database records should change.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None
## UC-21: Filter by Delivery Method
### TC-21-01: Filter by Shipping

**Use Case:** UC-21

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that selecting Shipping returns only listings
that support shipping.

**Preconditions:**
- OmniBuy is running in Docker.
- Active shipping and meetup listings exist.

**Test Data:**
- Selected delivery method: Shipping
- Active listings with shipping and meetup delivery methods

**Test Steps:**
1. Open Browse All Listings.
2. Select Shipping under Delivery Method.
3. Click Apply Filters.
4. Examine the returned listings.

**Expected Result:**
- Only active shipping listings should appear.
- Meetup listings should not appear.
- The Shipping radio button should remain selected.
- The Meetup Distance filter should be hidden.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-21-02: Filter by Meetup

**Use Case:** UC-21

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that selecting Meetup returns only listings
that support meetup delivery.

**Preconditions:**
- OmniBuy is running in Docker.
- Active shipping and meetup listings exist.

**Test Data:**
- Selected delivery method: Meetup
- Active listings with shipping and meetup delivery methods

**Test Steps:**
1. Open Browse All Listings.
2. Select Meetup under Delivery Method.
3. Click Apply Filters.
4. Examine the returned listings.

**Expected Result:**
- Only active meetup listings should appear.
- Shipping listings should not appear.
- The Meetup radio button should remain selected.
- The Meetup Distance filter should remain visible.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-21-03: Search Without a Delivery Method Filter

**Use Case:** UC-21

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that leaving both delivery methods unselected
allows shipping and meetup listings to appear together.

**Preconditions:**
- OmniBuy is running in Docker.
- Active shipping and meetup listings exist.

**Test Data:**
- Delivery Method: No selection
- Distance Range: Any Distance
- Active shipping and meetup listings

**Test Steps:**
1. Open Browse All Listings.
2. Ensure neither Shipping nor Meetup is selected.
3. Leave Distance Range set to Any Distance.
4. Click Apply Filters.
5. Examine the returned listings.

**Expected Result:**
- Both active shipping and meetup listings should appear.
- Neither delivery method radio button should be selected.
- The Meetup Distance filter should be visible.
- No delivery method restrictions should be applied.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-21-04: Combine Delivery Method and Condition Filters

**Use Case:** UC-21, UC-22

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that the delivery method filter works correctly
when combined with a condition filter.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different conditions and
  delivery methods exist.

**Test Data:**
- Selected delivery method: Shipping
- Selected condition: Good
- Active listings with different combinations of
  delivery methods and conditions

**Test Steps:**
1. Open Browse All Listings.
2. Select Shipping under Delivery Method.
3. Select Good under Condition.
4. Click Apply Filters.
5. Examine the returned listings.

**Expected Result:**
- Only active shipping listings with Good condition
  should appear.
- Meetup listings should not appear.
- Listings with other conditions should not appear.
- Both filter selections should remain selected.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-21-05: Clear Delivery Method Filter

**Use Case:** UC-21

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that clearing the delivery method filter
restores results without delivery restrictions.

**Preconditions:**
- OmniBuy is running in Docker.
- Active shipping and meetup listings exist.

**Test Data:**
- Selected delivery method: Shipping
- Active shipping and meetup listings

**Test Steps:**
1. Open Browse All Listings.
2. Select Shipping under Delivery Method.
3. Click Apply Filters.
4. Verify that only shipping listings appear.
5. Click Clear.
6. Examine the updated search results.

**Expected Result:**
- The Shipping radio button should become unselected.
- The Meetup radio button should remain unselected.
- Both shipping and meetup listings should become
  eligible to appear.
- The Meetup Distance filter should become visible.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None
## UC-22: Filter by Condition
### TC-22-01: Filter by Single Condition

**Use Case:** UC-22

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that selecting a single condition returns
only listings matching that condition.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with multiple conditions exist.

**Test Data:**
- Selected condition: Good
- Active listings with Good and other conditions

**Test Steps:**
1. Open Browse All Listings.
2. Select the Good condition checkbox.
3. Click Apply Filters.
4. Examine the returned listings.

**Expected Result:**
Only active listings with the Good condition
should be displayed.

**Actual Result:**
Automated test passed. The number of active good-condition
listings returned by the search page matched the
database count.

**Status:** Pass

**Related Jira Defect:** None

### TC-22-02: Filter by Multiple Conditions

**Use Case:** UC-22

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that selecting multiple conditions returns listings
matching any of the selected conditions.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different conditions exist.
- At least one active listing exists for each selected condition.

**Test Data:**
- Selected conditions: New and Good
- Active listings with New, Good, and other conditions

**Test Steps:**
1. Open Browse All Listings.
2. Select the New condition checkbox.
3. Select the Good condition checkbox.
4. Click Apply Filters.
5. Examine the returned listings.

**Expected Result:**
- Only active listings with New or Good condition should appear.
- Listings with other conditions should not appear.
- Both condition checkboxes should remain selected.

**Actual Result:**
The automated test confirmed that the search result count
matched the database count when two condition filters
were applied simultaneously.

**Status:** Pass

**Related Jira Defect:** None


### TC-22-03: Combine Condition and Delivery Method Filters

**Use Case:** UC-22, UC-21

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that condition filtering works correctly when combined
with the delivery method filter.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different conditions and delivery methods exist.

**Test Data:**
- Selected condition: Good
- Selected delivery method: Meetup
- Active listings with different combinations of conditions
  and delivery methods

**Test Steps:**
1. Open Browse All Listings.
2. Select the Good condition checkbox.
3. Select Meetup under Delivery Method.
4. Click Apply Filters.
5. Examine the returned listings.

**Expected Result:**
- Only active listings with Good condition and Meetup
  delivery method should appear.
- Shipping listings should not appear.
- Listings with other conditions should not appear.
- Both filter selections should remain selected.

**Actual Result:**
The automated test verified that applying condition and
delivery method filters together returned the expected
number of active listings.

The result count matched the corresponding MySQL query.

**Status:** Pass

**Related Jira Defect:** None


### TC-22-04: Clear Condition Filters

**Use Case:** UC-22

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that clearing condition filters restores listings
without condition restrictions.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different conditions exist.

**Test Data:**
- Selected conditions: New and Good
- Active listings with multiple conditions

**Test Steps:**
1. Open Browse All Listings.
2. Select the New and Good condition checkboxes.
3. Click Apply Filters.
4. Verify that only matching listings appear.
5. Click Clear.
6. Examine the updated search results.

**Expected Result:**
- All condition checkboxes should become unchecked.
- The condition restriction should be removed.
- Listings with other conditions should become eligible
  to appear in the results.
- The search should not produce an error.

**Actual Result:**
The automated test confirmed that removing the condition
filter restores the full active listing result count.

The manual test confirmed that clicking Clear removes
the selected condition checkboxes and updates the
search results.

**Status:** Pass

**Related Jira Defect:** None


### TC-22-05: Filter With No Matching Results

**Use Case:** UC-22

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that the search page handles condition filters
that produce no matching listings.

**Preconditions:**
- OmniBuy is running in Docker.
- Test data is available where no active listings match
  the selected search criteria.

**Test Data:**
- Selected condition: Poor
- A search keyword with no active Poor-condition matches

**Test Steps:**
1. Open the marketplace search page.
2. Search for a keyword known to have no active listings
   with Poor condition.
3. Select the Poor condition checkbox.
4. Click Apply Filters.
5. Examine the search results area.

**Expected Result:**
- No listings should appear.
- The result count should display 0 results.
- The page should display the No Listings Found message.
- The Poor checkbox should remain selected.
- No database or PHP errors should occur.

**Actual Result:**
The automated test confirmed that a search with no matching
listings displayed 0 results and the No listings found message.

The Poor condition checkbox remained selected.

**Status:** Pass

**Related Jira Defect:** None
## UC-23: Radius Filter for Meetup
### TC-23-01: Filter Meetup Listings Within a Radius

**Use Case:** UC-23

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that entering a valid ZIP code and selecting a radius
returns meetup listings within the specified distance.

**Preconditions:**
- OmniBuy is running in Docker.
- The zip_code_locations table contains valid ZIP data.
- Active meetup listings with known coordinates exist.

**Test Data:**
- Buyer ZIP code: 92831
- Distance Range: Within 10 miles
- Delivery Method: Meetup
- Active meetup listings located within 10 miles

**Test Steps:**
1. Open Browse All Listings.
2. Select Meetup under Delivery Method.
3. Enter ZIP code 92831.
4. Select Within 10 miles.
5. Click Apply Filters.
6. Examine the returned listings.

**Expected Result:**
- Active meetup listings within 10 miles should appear.
- Listings outside the radius should not appear.
- Shipping listings should not appear.
- The ZIP code and radius selection should remain displayed.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-02: Exclude Meetup Listings Outside the Radius

**Use Case:** UC-23

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that meetup listings beyond the selected distance
are excluded from the search results.

**Preconditions:**
- OmniBuy is running in Docker.
- Active meetup listings exist both inside and outside
  the selected radius.

**Test Data:**
- Buyer ZIP code: 92831
- Distance Range: Within 10 miles
- Delivery Method: Meetup
- Active meetup listings located more than 10 miles away

**Test Steps:**
1. Open Browse All Listings.
2. Select Meetup under Delivery Method.
3. Enter ZIP code 92831.
4. Select Within 10 miles.
5. Click Apply Filters.
6. Compare the results against known listing distances.

**Expected Result:**
- Meetup listings within 10 miles should be included.
- Meetup listings farther than 10 miles should be excluded.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-03: Test Radius Boundary Conditions

**Use Case:** UC-23

**Test Type:** Automated Unit / Automated Integration / Boundary Testing

**Objective:**
Verify that listings exactly at the selected radius boundary
are included and listings just beyond it are excluded.

**Preconditions:**
- An isolated test database is available.
- Controlled meetup listings with predetermined
  coordinates are available.
- Expected distances have been independently calculated.

**Test Data:**
- Distance Range: Within 10 miles
- Listing A: Less than 10 miles away
- Listing B: Exactly 10 miles away
- Listing C: Slightly more than 10 miles away

**Test Steps:**
1. Prepare controlled meetup listings at the test distances.
2. Apply the 10-mile radius filter using the test location.
3. Examine which listings are returned.
4. Compare the results against the predetermined distances.

**Expected Result:**
- Listing A should appear.
- Listing B should appear because it is at the boundary.
- Listing C should not appear.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-04: Handle Missing Buyer ZIP Code

**Use Case:** UC-23

**Test Type:** Manual Functional / Negative Testing

**Objective:**
Verify that selecting a radius without entering a ZIP code
produces an appropriate validation error.

**Preconditions:**
- OmniBuy is running in Docker.
- The radius filter is available.

**Test Data:**
- Buyer ZIP code: Blank
- Distance Range: Within 10 miles
- Delivery Method: Meetup

**Test Steps:**
1. Open Browse All Listings.
2. Select Meetup under Delivery Method.
3. Leave the ZIP-code field empty.
4. Select Within 10 miles.
5. Click Apply Filters.
6. Examine the search results area.

**Expected Result:**
- An error message should request a valid five-digit ZIP code.
- The application should not execute an unfiltered listing
  search as though the radius were valid.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-05: Handle Invalid or Unknown ZIP Code

**Use Case:** UC-23

**Test Type:** Manual Functional / Automated Integration / Negative Testing

**Objective:**
Verify that invalid ZIP-code formats and ZIP codes
missing from the reference table are handled appropriately.

**Preconditions:**
- OmniBuy is running in Docker.
- The zip_code_locations table is populated.

**Test Data:**
- Invalid ZIP format: 12A45
- Unknown ZIP: A valid five-digit value confirmed
  to be absent from zip_code_locations
- Distance Range: Within 10 miles

**Test Steps:**
1. Open Browse All Listings.
2. Select Meetup under Delivery Method.
3. Enter the invalid ZIP code 12A45.
4. Select Within 10 miles.
5. Attempt to apply the filters.
6. Verify that the invalid input is rejected.
7. Replace the ZIP with a five-digit code not found
   in the lookup table.
8. Apply the filters again.
9. Examine the error message.

**Expected Result:**
- Invalid ZIP-code formats should be rejected.
- Unknown ZIP codes should produce an appropriate error.
- The search should not silently ignore the requested radius.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-06: Test All Supported Radius Selections

**Use Case:** UC-23

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that each supported radius correctly restricts
meetup listings based on distance.

**Preconditions:**
- OmniBuy is running in Docker.
- Active meetup listings exist at different distances.
- The buyer ZIP code is valid.

**Test Data:**
- Buyer ZIP code: 92831
- Supported radii: 5, 10, 25, and 50 miles
- Active meetup listings at known distances

**Test Steps:**
1. Open Browse All Listings.
2. Select Meetup under Delivery Method.
3. Enter ZIP code 92831.
4. Select Within 5 miles and apply the filters.
5. Record the returned listings.
6. Repeat with 10, 25, and 50 miles.
7. Compare each result set against known listing distances.

**Expected Result:**
- Each radius should include only eligible meetup listings
  within the selected distance.
- Increasing the radius should not remove listings that
  were included at a smaller radius.
- Listings beyond the selected radius should be excluded.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-07: Preserve Shipping Listings During Radius Filtering

**Use Case:** UC-23, UC-21

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that radius filtering restricts meetup listings
without removing shipping listings when no delivery
method is selected.

**Preconditions:**
- OmniBuy is running in Docker.
- Active shipping and meetup listings exist.
- Meetup listings exist inside and outside the radius.

**Test Data:**
- Buyer ZIP code: 92831
- Distance Range: Within 10 miles
- Delivery Method: No selection

**Test Steps:**
1. Open Browse All Listings.
2. Leave Shipping and Meetup unselected.
3. Enter ZIP code 92831.
4. Select Within 10 miles.
5. Click Apply Filters.
6. Examine the returned listings.

**Expected Result:**
- All matching active shipping listings should remain visible.
- Meetup listings within 10 miles should appear.
- Meetup listings beyond 10 miles should be excluded.
- The radius should not restrict shipping listings.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-08: Handle Meetup Listings With Missing Coordinates

**Use Case:** UC-23

**Test Type:** Automated Integration / Negative Testing

**Objective:**
Verify that meetup listings without usable location
coordinates are not incorrectly included in radius results.

**Preconditions:**
- An isolated test database is available.
- Controlled meetup listings with missing coordinates exist.
- The buyer ZIP code is valid.

**Test Data:**
- Buyer ZIP code: 92831
- Distance Range: Within 10 miles
- Meetup listing with NULL latitude
- Meetup listing with NULL longitude
- Meetup listing with valid coordinates

**Test Steps:**
1. Prepare controlled meetup listings with the specified data.
2. Select Meetup under Delivery Method.
3. Enter ZIP code 92831.
4. Select Within 10 miles.
5. Apply the filters.
6. Examine the returned listing IDs.

**Expected Result:**
- Listings with missing coordinates should not appear
  in the radius-filtered meetup results.
- Listings with valid coordinates should be evaluated
  using their calculated distances.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-09: Verify Distance Calculation Accuracy

**Use Case:** UC-23

**Test Type:** Automated Unit / Automated Integration

**Objective:**
Verify that the distance calculation produces expected
results for known geographic coordinates.

**Preconditions:**
- An isolated test database is available.
- ZIP-code coordinates and controlled listing
  coordinates are known.
- Expected distances have been independently calculated.

**Test Data:**
- Buyer ZIP code: 92831
- Meetup listings with known latitude and longitude
- Predetermined expected distances in miles

**Test Steps:**
1. Retrieve the coordinates for the buyer ZIP code.
2. Calculate distances using MySQL ST_Distance_Sphere.
3. Convert the calculated distances from meters to miles.
4. Compare the output with independently calculated
   expected distances.
5. Verify that the values fall within an agreed
   numerical tolerance.

**Expected Result:**
- Calculated distances should match expected values
  within the defined tolerance.
- Distance units should be converted correctly.
- The same coordinates should produce repeatable results.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-23-10: Combine Radius Filtering With Other Search Filters

**Use Case:** UC-23, UC-21, UC-22, UC-24

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that radius filtering works correctly alongside
condition filtering and distance sorting.

**Preconditions:**
- OmniBuy is running in Docker.
- Active meetup listings with different conditions
  and known coordinates exist.
- The ZIP-code lookup table is populated.

**Test Data:**
- Buyer ZIP code: 92831
- Distance Range: Within 25 miles
- Delivery Method: Meetup
- Condition: Good
- Sort option: Distance: Near to Far

**Test Steps:**
1. Open Browse All Listings.
2. Select Meetup under Delivery Method.
3. Select Good under Condition.
4. Enter ZIP code 92831.
5. Select Within 25 miles.
6. Click Apply Filters.
7. Select Distance: Near to Far.
8. Examine the returned results and selected filters.

**Expected Result:**
- Only active meetup listings with Good condition
  within 25 miles should appear.
- Results should be ordered nearest to farthest.
- ZIP code, radius, condition, and delivery selections
  should remain applied after sorting.
- No unrelated listings should appear.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None
## UC-24: Sort Results
### TC-24-01: Sort by Price Low to High

**Use Case:** UC-24

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that listings are sorted by price in ascending order.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different prices exist.

**Test Data:**
- Sort option: Price Low to High
- Active listings with known prices

**Test Steps:**
1. Open Browse All Listings.
2. Select Price: Low to High from the sorting dropdown.
3. Examine the order of the returned listings.
4. Compare listing prices from top to bottom.

**Expected Result:**
- Listings should appear in ascending price order.
- The lowest-priced listings should appear first.
- The highest-priced listings should appear last.
- The selected sorting option should remain displayed.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-24-02: Sort by Price High to Low

**Use Case:** UC-24

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that listings are sorted by price in descending order.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different prices exist.

**Test Data:**
- Sort option: Price High to Low
- Active listings with known prices

**Test Steps:**
1. Open Browse All Listings.
2. Select Price: High to Low from the sorting dropdown.
3. Examine the order of the returned listings.
4. Compare listing prices from top to bottom.

**Expected Result:**
- Listings should appear in descending price order.
- The highest-priced listings should appear first.
- The lowest-priced listings should appear last.
- The selected sorting option should remain displayed.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-24-03: Sort by Newest

**Use Case:** UC-24

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that listings are sorted from newest to oldest
based on their creation timestamps.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different creation timestamps exist.

**Test Data:**
- Sort option: Newest
- Active listings with known created_at values

**Test Steps:**
1. Open Browse All Listings.
2. Select Newest from the sorting dropdown.
3. Examine the returned listing order.
4. Compare the listing order against the created_at
   timestamps stored in the database.

**Expected Result:**
- Listings with the most recent creation timestamps
  should appear first.
- Older listings should appear afterward.
- Listings with identical timestamps should be ordered
  by listing ID in descending order.
- The Newest option should remain selected.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-24-04: Sort by Oldest

**Use Case:** UC-24

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that listings are sorted from oldest to newest
based on their creation timestamps.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different creation timestamps exist.

**Test Data:**
- Sort option: Oldest
- Active listings with known created_at values

**Test Steps:**
1. Open Browse All Listings.
2. Select Oldest from the sorting dropdown.
3. Examine the returned listing order.
4. Compare the listing order against the created_at
   timestamps stored in the database.

**Expected Result:**
- Listings with the earliest creation timestamps
  should appear first.
- Newer listings should appear afterward.
- Listings with identical timestamps should be ordered
  by listing ID in ascending order.
- The Oldest option should remain selected.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-24-05: Sort Results With Active Filters

**Use Case:** UC-24, UC-21, UC-22

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that changing the sorting option does not remove
or interfere with previously applied search filters.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings with different conditions, prices,
  and delivery methods exist.

**Test Data:**
- Selected condition: Good
- Selected delivery method: Meetup
- Sort option: Price Low to High

**Test Steps:**
1. Open Browse All Listings.
2. Select Good under Condition.
3. Select Meetup under Delivery Method.
4. Click Apply Filters.
5. Verify that the correct filtered listings appear.
6. Change the sorting option to Price: Low to High.
7. Examine the returned listings and selected filters.

**Expected Result:**
- Only active listings with Good condition and
  Meetup delivery method should appear.
- Matching listings should be sorted by price
  from lowest to highest.
- The Good checkbox should remain selected.
- The Meetup radio button should remain selected.
- No unrelated listings should appear.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-24-06: Sort by Distance Near to Far

**Use Case:** UC-24, UC-23

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that meetup listings are sorted from nearest
to farthest based on the buyer's ZIP-code location.

**Preconditions:**
- OmniBuy is running in Docker.
- The zip_code_locations table contains valid ZIP data.
- Active meetup listings with latitude and longitude exist.
- Active shipping listings exist.

**Test Data:**
- Buyer ZIP code: 92831
- Delivery method: No selection
- Distance Range: Any Distance
- Sort option: Distance: Near to Far (Meetup First)
- Active meetup listings with known coordinates

**Test Steps:**
1. Open Browse All Listings.
2. Leave both delivery method radio buttons unselected.
3. Enter ZIP code 92831.
4. Leave Distance Range set to Any Distance.
5. Click Apply Filters.
6. Select Distance: Near to Far (Meetup First).
7. Examine the returned listing order.
8. Run a SQL distance calculation using ZIP code 92831.
9. Compare the website listing order against the SQL results.

**Expected Result:**
- Meetup listings with valid coordinates should
  appear before shipping listings.
- Meetup listings should be ordered from nearest
  to farthest.
- Shipping listings should appear after meetup listings.
- Shipping listings should be ordered newest first.
- The ZIP code should remain entered after sorting.
- The calculated ordering should match the SQL results.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-24-07: Handle Unsupported Sorting Option

**Use Case:** UC-24

**Test Type:** Automated Unit / Manual Negative Testing

**Objective:**
Verify that unsupported sorting parameters are handled
safely without causing database errors.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist.

**Test Data:**
- Invalid sort parameter: invalid_sort
- Valid default sort parameter: recent

**Test Steps:**
1. Open the Browse All Listings page.
2. Manually modify the URL to include:
   ?browse=all&sort=invalid_sort
3. Load the modified URL.
4. Examine the returned listings and sorting dropdown.

**Expected Result:**
- The unsupported sorting parameter should be rejected.
- The application should fall back to Newest sorting.
- Active listings should still display correctly.
- The sorting dropdown should show Newest.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None