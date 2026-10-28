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

## UC-16: Browse Marketplace Feed
### TC-16-01: Display Active Listings in Browse Feed

**Use Case:** UC-16

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that the marketplace browse feed displays
active listings available to buyers.

**Preconditions:**
- OmniBuy is running in Docker.
- Seed data contains active listings.

**Test Data:**
- At least one active seeded listing
- At least one inactive seeded listing

**Test Steps:**
1. Open the OmniBuy marketplace home page.
2. Examine the listings displayed in the browse feed.
3. Compare the displayed listings against active listings
   stored in the database.
4. Confirm that at least one known active listing appears.

**Expected Result:**
- Active marketplace listings should be displayed.
- Listing information should correspond to the stored data.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-16-02: Exclude Inactive Listings From Browse Feed

**Use Case:** UC-16

**Test Type:** Manual Functional / Automated Functional / Negative Testing

**Objective:**
Verify that inactive listings are excluded from the
marketplace browse feed.

**Preconditions:**
- OmniBuy is running in Docker.
- Seed data contains listings with active and inactive statuses.

**Test Data:**
- Known active listing
- Known hidden, sold, archived, or draft listing

**Test Steps:**
1. Open the marketplace browse feed.
2. Identify the known active listing.
3. Search the rendered feed for the known inactive listing.
4. Compare the displayed listings against database status values.

**Expected Result:**
- Active listings should remain eligible to appear.
- Inactive listings should not appear in the browse feed.
- No inactive listing data should be exposed as an
  available marketplace item.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-16-03: Browse All Active Listings

**Use Case:** UC-16

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that Browse All displays active marketplace listings
without requiring a search keyword.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist.

**Test Data:**
- Browse mode: All Listings
- Known active seeded listings

**Test Steps:**
1. Open Browse All Listings.
2. Leave the search keyword blank.
3. Examine the returned listing cards.
4. Compare the results with active listings in the database.

**Expected Result:**
- Active listings should be displayed without a keyword.
- The page should not remain in the initial search state.
- Inactive listings should remain excluded.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-16-04: Handle Browse Feed With No Eligible Listings

**Use Case:** UC-16

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that the browse page handles a state where no
eligible active listings are available.

**Preconditions:**
- An isolated test database is available.
- No active listings exist in the controlled test data.

**Test Data:**
- Listings with only inactive statuses

**Test Steps:**
1. Prepare the test database with no active listings.
2. Open the marketplace browse page.
3. Examine the result count and empty-state content.
4. Check for PHP or database errors.

**Expected Result:**
- The page should not fail.
- No inactive listings should be displayed as available.
- An appropriate empty state should be shown.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


## UC-17: Search for Items
### TC-17-01: Search for an Existing Item

**Use Case:** UC-17

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that searching for an existing item returns the
matching active listing.

**Preconditions:**
- OmniBuy is running in Docker.
- A known active listing exists.

**Test Data:**
- Search type: Items
- Exact item name from an active seeded listing

**Test Steps:**
1. Open the marketplace search page.
2. Select Item Search.
3. Enter the exact item name.
4. Submit the search.
5. Examine the returned listings.

**Expected Result:**
- The matching active listing should appear.
- The No Listings Found message should not appear.
- The search query should remain visible.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-17-02: Search Using a Partial Item Name

**Use Case:** UC-17

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that partial keyword searches can return relevant
active item listings.

**Preconditions:**
- OmniBuy is running in Docker.
- A known active listing exists.

**Test Data:**
- Partial word from a known active item name

**Test Steps:**
1. Open Item Search.
2. Enter a partial word from the seeded item name.
3. Submit the search.
4. Examine the returned listings.

**Expected Result:**
- The expected active listing should appear when the
  partial keyword matches its searchable data.
- Inactive listings should remain excluded.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-17-03: Display No-Results State for Item Search

**Use Case:** UC-17

**Test Type:** Manual Functional / Automated Functional / Negative Testing

**Objective:**
Verify that Item Search displays an appropriate empty state
when no active listing matches the keyword.

**Preconditions:**
- OmniBuy is running in Docker.

**Test Data:**
- Search keyword known not to match any active listing

**Test Steps:**
1. Open Item Search.
2. Enter a keyword that does not match any active listing.
3. Submit the search.
4. Examine the results area.

**Expected Result:**
- The result count should indicate no matching listings.
- The page should display No Listings Found.
- No unrelated listing should be displayed as a match.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-17-04: Exclude Inactive Listings From Item Search

**Use Case:** UC-17

**Test Type:** Manual Functional / Automated Functional / Negative Testing

**Objective:**
Verify that Item Search does not return listings that are
hidden, sold, archived, draft, or otherwise inactive.

**Preconditions:**
- OmniBuy is running in Docker.
- A known inactive listing exists with a unique item name.

**Test Data:**
- Exact item name of a known inactive listing

**Test Steps:**
1. Open Item Search.
2. Search for the exact inactive item name.
3. Submit the search.
4. Examine the returned state.

**Expected Result:**
- The inactive listing should not be returned as a result.
- The page should display No Listings Found when no active
  listing matches the query.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-17-05: Search by Category Name Keyword

**Use Case:** UC-17

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that item search can return active listings when the
keyword matches the listing's category name.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings are assigned to searchable categories.

**Test Data:**
- Category name associated with active listings

**Test Steps:**
1. Open Item Search.
2. Enter a category name as the search keyword.
3. Submit the search.
4. Examine the returned listings.

**Expected Result:**
- Active listings in matching categories should be eligible
  to appear in the results.
- Inactive listings should remain excluded.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


## UC-18: Search Seller Profiles
### TC-18-01: Search for an Existing Seller

**Use Case:** UC-18

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that searching for an active seller returns the
matching seller profile.

**Preconditions:**
- OmniBuy is running in Docker.
- An active seller profile exists.

**Test Data:**
- Exact store name of an active seller

**Test Steps:**
1. Open Marketplace Search.
2. Select Seller Profiles.
3. Enter the exact store name.
4. Submit the search.
5. Examine the returned seller profiles.

**Expected Result:**
- The matching active seller should appear.
- The No Sellers Found message should not appear.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-18-02: Search Seller Using a Partial Name

**Use Case:** UC-18

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that partial seller queries return relevant
active seller profiles.

**Preconditions:**
- OmniBuy is running in Docker.
- An active seller profile exists.

**Test Data:**
- Partial store name or username

**Test Steps:**
1. Open Seller Search.
2. Enter a partial seller query.
3. Submit the search.
4. Examine the returned profiles.

**Expected Result:**
- The expected active seller should appear when the query
  matches a searchable seller field.
- No unrelated inactive seller should appear.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-18-03: Search Seller by Username or Visible Name

**Use Case:** UC-18

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that seller search supports store name, username,
first name, and permitted visible name fields.

**Preconditions:**
- OmniBuy is running in Docker.
- Active sellers with known profile data exist.

**Test Data:**
- Seller username
- Seller first name
- Seller visible last name or full name

**Test Steps:**
1. Search for the seller by username.
2. Record the returned result.
3. Repeat using the seller's first name.
4. If the last name is visible, repeat using the last name
   and full name.
5. Compare the returned profile with the expected seller.

**Expected Result:**
- Supported public seller fields should return the
  corresponding active seller.
- Hidden last names should not be exposed as searchable
  public profile data.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-18-04: Display No-Results State for Seller Search

**Use Case:** UC-18

**Test Type:** Manual Functional / Automated Functional / Negative Testing

**Objective:**
Verify that Seller Search displays an appropriate empty state
when no active seller matches the query.

**Preconditions:**
- OmniBuy is running in Docker.

**Test Data:**
- Seller query known not to match any active seller

**Test Steps:**
1. Open Seller Search.
2. Enter a seller query with no active match.
3. Submit the search.
4. Examine the results area.

**Expected Result:**
- No seller cards should be displayed.
- The page should display No Sellers Found.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-18-05: Exclude Inactive Sellers From Search

**Use Case:** UC-18

**Test Type:** Manual Functional / Automated Functional / Negative Testing

**Objective:**
Verify that seller accounts that are suspended or otherwise
inactive are excluded from Seller Search.

**Preconditions:**
- OmniBuy is running in Docker.
- A seeded inactive seller account exists.

**Test Data:**
- Exact store name of an inactive seller

**Test Steps:**
1. Open Seller Search.
2. Search for the inactive seller's exact store name.
3. Submit the search.
4. Examine the returned state.

**Expected Result:**
- The inactive seller should not be returned.
- The page should display No Sellers Found when there are
  no other active matches.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-18-06: Protect Restricted Seller Data

**Use Case:** UC-18

**Test Type:** Automated Functional / Security Regression

**Objective:**
Verify that Seller Search does not expose restricted
account or payout information.

**Preconditions:**
- OmniBuy is running in Docker.
- An active seller with known restricted values exists.

**Test Data:**
- Seller password hash
- Seller birth date
- Seller payout account reference

**Test Steps:**
1. Search for the active seller.
2. Capture the returned HTML.
3. Compare the response against the seller's restricted
   database values.
4. Search the response for restricted internal field names.

**Expected Result:**
- Password hashes should not appear in the response.
- Birth dates should not appear in the response.
- Payout account references should not appear.
- Restricted internal field names should not be rendered.
- Only intended public seller information should appear.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


## UC-19: Filter Listings by Category
**Automated PHPUnit source:** `tests/Functional/CategoryFilterTest.php`  
**Execution evidence:** User-confirmed successful targeted PHPUnit tests and complete PHPUnit suite. Automated verification covers 11 test methods; UI-only or deferred scenarios are identified separately below.

### TC-19-01: Filter by a Child Category

**Use Case:** UC-19

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that selecting a child category returns active
listings assigned to that category.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist in multiple categories.
- At least one child category contains active listings.

**Test Data:**
- Selected child category
- Active listings assigned to that category

**Test Steps:**
1. Open Browse All Listings.
2. Select a child category from the Category dropdown.
3. Click Apply Filters.
4. Examine the returned listings.

**Expected Result:**
- Active listings in the selected child category should appear.
- Listings from unrelated categories should not appear.
- The selected category should remain displayed.
- No PHP or database errors should occur.

**Actual Result:**
The `testSubcategoryDisplaysItsListingsButNotSiblingListings` PHPUnit test verified an active Computers item appeared, an Audio item was absent, and the displayed count matched MySQL. The selection UI was previously confirmed manually.

**Status:** Pass

**Related Jira Defect:** None


### TC-19-02: Filter by a Parent Category

**Use Case:** UC-19

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that selecting a parent category includes eligible
listings from that category and its direct child categories.

**Preconditions:**
- OmniBuy is running in Docker.
- A parent category has one or more direct child categories.
- Active listings exist in the parent or child categories.

**Test Data:**
- Selected parent category
- Active listings in its direct child categories

**Test Steps:**
1. Open Browse All Listings.
2. Select a parent category.
3. Click Apply Filters.
4. Examine the returned listings.
5. Compare the results against the category hierarchy.

**Expected Result:**
- Active listings directly assigned to the parent should
  be eligible to appear.
- Active listings in direct child categories should appear.
- Listings from unrelated categories should not appear.
- No PHP or database errors should occur.

**Actual Result:**
The `testParentCategoryIncludesListingsFromBothSubcategories` PHPUnit test confirmed Electronics includes Computers and Audio listings, and its count matches MySQL.

**Status:** Pass

**Related Jira Defect:** None


### TC-19-03: Apply Category Filter Without a Keyword

**Use Case:** UC-19

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that category filtering works independently
without requiring a search keyword.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist in the selected category.

**Test Data:**
- Search keyword: Blank
- Selected valid category

**Test Steps:**
1. Open the marketplace search page.
2. Leave the keyword blank.
3. Select a category.
4. Click Apply Filters.
5. Examine the returned listings.

**Expected Result:**
- Category-filtered listings should be displayed.
- The page should not remain in the Start Searching state.
- The category should remain selected.
- No PHP or database errors should occur.

**Actual Result:**
The `testLeafCategoryWithoutSubcategoriesDisplaysItsListings` and main-category count tests submitted category filters without a keyword; results appeared with the expected database counts.

**Status:** Pass

**Related Jira Defect:** None


### TC-19-04: Combine Keyword Search and Category Filter

**Use Case:** UC-19, UC-17

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that category filtering works together with
an item keyword search.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist with different categories
  and searchable keywords.

**Test Data:**
- Item keyword
- Selected category containing a matching listing

**Test Steps:**
1. Enter an item search keyword.
2. Select a category.
3. Apply the filters.
4. Examine the returned listings.

**Expected Result:**
- Only active listings matching both the search criteria
  and selected category should appear.
- The keyword and category selection should be preserved.
- No unrelated category results should appear.
- No PHP or database errors should occur.

**Actual Result:**
The `testMatchingKeywordWithinCategoryFindsTheListing` PHPUnit test located a known active book by its name within the Books category and did not display the no-results message.

**Status:** Pass

**Related Jira Defect:** None


### TC-19-05: Handle Invalid Category Parameter

**Use Case:** UC-19

**Test Type:** Automated Functional / Manual Negative Testing

**Objective:**
Verify that invalid or nonexistent category identifiers
are handled safely.

**Preconditions:**
- OmniBuy is running in Docker.

**Test Data:**
- Non-numeric category parameter
- Nonexistent numeric category ID

**Test Steps:**
1. Open the search page with a non-numeric category value.
2. Examine the returned page.
3. Repeat with a numeric category ID that does not exist.
4. Examine the results and selected category state.

**Expected Result:**
- Invalid category values should not cause SQL or PHP errors.
- Nonexistent categories should not be applied as valid filters.
- The application should fall back safely to an unselected
  category state.
- No unsafe SQL behavior should occur.

**Actual Result:**
Not covered by `CategoryFilterTest.php`: the dropdown does not allow arbitrary values. Direct-request invalid-parameter regression testing is deferred.

Execution result not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-19-06: Clear Category Filter

**Use Case:** UC-19

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that clearing the category filter removes the
category restriction.

**Preconditions:**
- OmniBuy is running in Docker.
- A category filter has been applied.

**Test Data:**
- Selected category with matching active listings

**Test Steps:**
1. Apply a valid category filter.
2. Verify the filtered results.
3. Click Clear.
4. Examine the Category dropdown and returned results.

**Expected Result:**
- The Category dropdown should return to All Categories.
- The category restriction should be removed.
- Listings from other eligible categories should become
  eligible to appear.
- No PHP or database errors should occur.

**Actual Result:**
The `testRemovingCategoryFilterRestoresBrowseAllResults` PHPUnit test simulated selecting All Categories and confirmed listings across multiple categories and the full active count. It did not automate clicking the JavaScript Clear button.

**Status:** Pass

**Related Jira Defect:** None


### TC-19-07: Include Trading Cards and Action Figures Under Collectibles

**Use Case:** UC-19

**Test Type:** Automated Functional

**Objective:**
Verify that the Collectibles parent category includes active listings from both Trading Cards and Action Figures.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Parent category: Collectibles
- Subcategories: Trading Cards and Action Figures

**Test Steps:**
1. Retrieve the three category IDs from MySQL.
2. Request Item Search filtered by Collectibles.
3. Find an active listing name from each subcategory in the rendered results.

**Expected Result:**
- At least one active trading-card listing is displayed.
- At least one active action-figure listing is displayed.

**Actual Result:**
The `testCollectiblesIncludesTradingCardsAndActionFigures` PHPUnit test found an active item from each subcategory in the Collectibles response.

**Status:** Pass

**Related Jira Defect:** None

### TC-19-08: Filter a Category Without Subcategories (Books)

**Use Case:** UC-19

**Test Type:** Automated Functional

**Objective:**
Verify that a leaf category without child categories displays its active listings and excludes unrelated products.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Category: Books
- Unrelated category: Computers

**Test Steps:**
1. Retrieve the Books and Computers IDs from MySQL.
2. Request search results with the Books filter.
3. Check a known active book is displayed and a known computer is absent.
4. Compare the result count with the active Books count in MySQL.

**Expected Result:**
- Active Books listings appear.
- Unrelated computer listings are excluded.
- The result count matches the active Books count.

**Actual Result:**
The `testLeafCategoryWithoutSubcategoriesDisplaysItsListings` PHPUnit test verified listing inclusion/exclusion and matched the MySQL result count.

**Status:** Pass

**Related Jira Defect:** None

### TC-19-09: Verify Active Listing Counts Across Main Categories

**Use Case:** UC-19

**Test Type:** Automated Functional

**Objective:**
Verify that each parent category displays the correct number of active listings from itself and its direct children.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Electronics
- Home and Furniture
- Clothing
- Books
- Collectibles

**Test Steps:**
1. Look up each main category ID in MySQL.
2. For each category, count active listings directly assigned to the category or its direct children.
3. Request category-filtered results for each main category.
4. Compare each displayed result count against MySQL.

**Expected Result:**
- Every displayed count matches the active listing count from MySQL.
- No inactive listings are included in the count.

**Actual Result:**
The `testEveryMainCategoryHasTheExpectedNumberOfActiveListings` PHPUnit test compared all five parent-category counts with corresponding MySQL counts.

**Status:** Pass

**Related Jira Defect:** None

### TC-19-10: Exclude Inactive Listings From Category Results

**Use Case:** UC-19

**Test Type:** Automated Functional

**Objective:**
Verify that a category filter never exposes hidden, sold, archived, or draft listings as active results.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- An existing inactive seeded listing
- The inactive listing’s category

**Test Steps:**
1. Select an inactive listing and its category from MySQL.
2. Request search results filtered by that category.
3. Confirm the inactive listing name is absent.
4. Compare the displayed count with the active count for the category.

**Expected Result:**
- The inactive listing is absent from displayed results.
- The result count includes only active listings.

**Actual Result:**
The `testInactiveListingIsExcludedFromItsCategory` PHPUnit test found no inactive item name and verified the expected active count.

**Status:** Pass

**Related Jira Defect:** None

### TC-19-11: Show No Results for Unmatched Keyword Within a Category

**Use Case:** UC-19

**Test Type:** Automated Functional

**Objective:**
Verify that a category-filtered keyword search displays an empty state if no listings match.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Category: Books
- Keyword: ci-no-such-category-item-948372

**Test Steps:**
1. Request Item Search with the Books category and the nonexistent keyword.
2. Read the returned empty-state message and result count.

**Expected Result:**
- The page displays `No listings found`.
- The result count is zero.

**Actual Result:**
The `testNonMatchingKeywordWithinCategoryDisplaysNoResults` PHPUnit test confirmed the no-results message and a zero result count.

**Status:** Pass

**Related Jira Defect:** None

### TC-19-12: Preserve Selected Category in Dropdown

**Use Case:** UC-19

**Test Type:** Automated Functional

**Objective:**
Verify that submitting a category filter retains the selected dropdown option in the HTML response.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Selected category: Action Figures

**Test Steps:**
1. Look up the Action Figures ID in MySQL.
2. Request category-filtered search results.
3. Inspect the matching HTML `<option>` tag.

**Expected Result:**
- The selected Action Figures option has its `selected` attribute.

**Actual Result:**
The `testSelectedCategoryRemainsSelectedInDropdown` PHPUnit test verified the selected option in the returned HTML.

**Status:** Pass

**Related Jira Defect:** None

### TC-19-13: Update Results After Changing Categories

**Use Case:** UC-19

**Test Type:** Automated Functional

**Objective:**
Verify that switching between two category choices updates the returned items without retaining unrelated items.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- First category: Books
- Second category: Computers

**Test Steps:**
1. Request Books-filtered results and record a known active book.
2. Request Computers-filtered results and record a known active computer.
3. Verify the book appears only in the Books response and the computer only in the Computers response.

**Expected Result:**
- Each category response includes its own expected listing.
- Neither response displays the known listing from the unrelated category.

**Actual Result:**
The `testChangingCategoryUpdatesTheDisplayedListings` PHPUnit test verified inclusion and exclusion across the two category responses.

**Status:** Pass

**Related Jira Defect:** None

## UC-20: Filter Listings by Price Range
**Automated PHPUnit source:** `tests/Functional/PriceRangeFilterTest.php`  
**Execution evidence:** User-confirmed successful targeted PHPUnit tests and complete PHPUnit suite. Automated verification covers 16 test methods; UI-only or deferred scenarios are identified separately below.

### TC-20-01: Filter by Minimum Price Only

**Use Case:** UC-20

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that entering only a minimum price returns listings
priced at or above the selected value.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist at different prices.

**Test Data:**
- Minimum price: Valid non-negative amount
- Maximum price: Blank

**Test Steps:**
1. Open Browse All Listings.
2. Enter a minimum price.
3. Leave the maximum price blank.
4. Click Apply Filters.
5. Examine the returned listing prices.

**Expected Result:**
- Only active listings priced at or above the minimum
  should appear.
- The minimum price should remain displayed.
- No maximum-price restriction should be applied.
- No PHP or database errors should occur.

**Actual Result:**
The `testMinimumPriceOnlyIncludesListingsAtOrAboveMinimum` PHPUnit test applied minimum 50 and verified the active MySQL count, included a qualifying item, and excluded an item below the minimum.

**Status:** Pass

**Related Jira Defect:** None


### TC-20-02: Filter by Maximum Price Only

**Use Case:** UC-20

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that entering only a maximum price returns listings
priced at or below the selected value.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist at different prices.

**Test Data:**
- Minimum price: Blank
- Maximum price: Valid non-negative amount

**Test Steps:**
1. Open Browse All Listings.
2. Leave the minimum price blank.
3. Enter a maximum price.
4. Click Apply Filters.
5. Examine the returned listing prices.

**Expected Result:**
- Only active listings priced at or below the maximum
  should appear.
- The maximum price should remain displayed.
- No minimum-price restriction should be applied.
- No PHP or database errors should occur.

**Actual Result:**
The `testMaximumPriceOnlyIncludesListingsAtOrBelowMaximum` PHPUnit test applied maximum 100 and verified the active MySQL count, included a qualifying item, and excluded an item above the maximum.

**Status:** Pass

**Related Jira Defect:** None


### TC-20-03: Filter by Minimum and Maximum Price

**Use Case:** UC-20

**Test Type:** Manual Functional / Automated Functional / Boundary Testing

**Objective:**
Verify that entering both price values returns listings
within the inclusive price range.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist below, within, and above the range.

**Test Data:**
- Minimum price: Valid amount
- Maximum price: Valid amount greater than or equal
  to the minimum

**Test Steps:**
1. Open Browse All Listings.
2. Enter a minimum price.
3. Enter a maximum price.
4. Click Apply Filters.
5. Examine the returned listing prices.

**Expected Result:**
- Listings below the minimum should not appear.
- Listings above the maximum should not appear.
- Listings exactly equal to either boundary should be
  eligible to appear.
- Both price values should remain displayed.
- No PHP or database errors should occur.

**Actual Result:**
The `testInclusiveMinimumAndMaximumPriceRange` PHPUnit test used 50–100, confirmed matching active listings, excluded a below-minimum item, and matched the inclusive MySQL count.

**Status:** Pass

**Related Jira Defect:** None


### TC-20-04: Reject Minimum Price Greater Than Maximum

**Use Case:** UC-20

**Test Type:** Manual Functional / Automated Functional / Negative Testing

**Objective:**
Verify that an invalid range where the minimum exceeds
the maximum is rejected safely.

**Preconditions:**
- OmniBuy is running in Docker.

**Test Data:**
- Minimum price greater than maximum price

**Test Steps:**
1. Open the marketplace search page.
2. Enter a minimum price larger than the maximum price.
3. Click Apply Filters.
4. Examine the results area.

**Expected Result:**
- The page should display Invalid Price Range.
- The message should explain that the minimum cannot
  exceed the maximum.
- The invalid range should not be executed as a listing query.
- No PHP or database errors should occur.

**Actual Result:**
The `testMinimumGreaterThanMaximumDisplaysValidationError` PHPUnit test submitted minimum 100 and maximum 50, then verified `Invalid Price Range` and the expected minimum-greater-than-maximum message.

**Status:** Pass

**Related Jira Defect:** None


### TC-20-05: Reject Invalid Price Input

**Use Case:** UC-20

**Test Type:** Automated Functional / Manual Negative Testing

**Objective:**
Verify that malformed, negative, or unsupported price
values are rejected safely.

**Preconditions:**
- OmniBuy is running in Docker.

**Test Data:**
- Negative price
- Non-numeric price submitted manually
- Price with more than two decimal places

**Test Steps:**
1. Submit each invalid price value through the applicable
   search request.
2. Examine the validation response.
3. Confirm that no invalid price value is used in SQL.
4. Repeat for minimum and maximum price inputs.

**Expected Result:**
- Invalid price values should be rejected.
- An appropriate Invalid Price Range message should appear.
- No malformed value should be applied to the listing query.
- No PHP or database errors should occur.

**Actual Result:**
The separate PHPUnit tests for negative minimum/maximum, nonnumeric minimum/maximum, and more than two decimal places all verified the appropriate `Invalid Price Range` heading and field-specific message. These assertions test validation responses rather than instrumenting SQL execution.

**Status:** Pass

**Related Jira Defect:** None


### TC-20-06: Combine Price Range With Category and Keyword

**Use Case:** UC-20, UC-19, UC-17

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that price filtering works together with category
filtering and keyword search.

**Preconditions:**
- OmniBuy is running in Docker.
- Active listings exist with different categories,
  searchable keywords, and prices.

**Test Data:**
- Item keyword
- Selected category
- Valid minimum and maximum price

**Test Steps:**
1. Enter an item search keyword.
2. Select a category.
3. Enter a valid minimum price.
4. Enter a valid maximum price.
5. Click Apply Filters.
6. Examine the returned listings.

**Expected Result:**
- Only active listings satisfying the keyword, category,
  and price constraints should appear.
- All applied values should remain preserved.
- No unrelated listing should appear.
- No PHP or database errors should occur.

**Actual Result:**
Not covered by `PriceRangeFilterTest.php`: this multi-filter combination remains deferred until combined Marketplace Discovery testing.

Execution result not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-20-07: Clear Price Range Filter

**Use Case:** UC-20

**Test Type:** Manual Functional 

**Objective:**
Verify that clearing filters removes both minimum and
maximum price restrictions.

**Preconditions:**
- OmniBuy is running in Docker.
- A price filter has been applied.

**Test Data:**
- Valid minimum price
- Valid maximum price

**Test Steps:**
1. Apply a valid price range.
2. Verify the filtered listings.
3. Click Clear.
4. Examine the price input fields and returned results.

**Expected Result:**
- Both price fields should become blank.
- The price restriction should be removed.
- Listings outside the previous range should become
  eligible to appear.
- No PHP or database errors should occur.

**Actual Result:**
Not covered by `PriceRangeFilterTest.php`: the suite verifies blank price fields and retained inputs, but it does not automate clicking the JavaScript Clear button.

Execution result not yet recorded.

Manual testing confirmed that clicking the Clear button
successfully removed the applied minimum and maximum
price filters.

Both price input fields were reset to blank, and the
search results updated to display eligible active listings
without the previously applied price restrictions.

No PHP or database errors occurred.

**Status:** Pass

**Related Jira Defect:** None


### TC-20-08: Include Listings at an Equal Minimum and Maximum Price

**Use Case:** UC-20

**Test Type:** Automated Functional

**Objective:**
Verify an inclusive price range with equal minimum and maximum values matches listings at that exact price.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum = maximum = the price of a real active seeded listing

**Test Steps:**
1. Retrieve a known active listing price from MySQL.
2. Request results with that price for both `min_price` and `max_price`.
3. Verify the listing name appears and the result count equals the active exact-price count.

**Expected Result:**
- Listings priced exactly at the boundary appear.
- The result count matches the number of active listings at that price.

**Actual Result:**
The `testEqualBoundsIncludeListingsAtThatExactPrice` PHPUnit test verified the selected listing appears and the count matches MySQL.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-09: Filter Using Two-Decimal Price Values

**Use Case:** UC-20

**Test Type:** Automated Functional

**Objective:**
Verify that valid monetary amounts with two decimals are accepted and filter results correctly.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum price: 19.99
- Maximum price: 49.99

**Test Steps:**
1. Request results with minimum 19.99 and maximum 49.99.
2. Count matching active listings directly in MySQL.
3. Compare the returned count and check for price validation errors.

**Expected Result:**
- The displayed count matches the inclusive decimal range in MySQL.
- `Invalid Price Range` is not displayed.

**Actual Result:**
The `testDecimalPriceRangeReturnsCorrectResults` PHPUnit test verified the count and absence of validation errors.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-10: Reject Negative Minimum Price

**Use Case:** UC-20

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that a negative minimum price is rejected by server-side validation.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum price: -10

**Test Steps:**
1. Request Item Search with `min_price=-10`.
2. Inspect the error heading and validation explanation.

**Expected Result:**
- The response displays `Invalid Price Range`.
- The response explains that the minimum must be a valid price with up to two decimal places.

**Actual Result:**
The `testNegativeMinimumPriceDisplaysValidationError` PHPUnit test found the expected heading and message.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-11: Reject Negative Maximum Price

**Use Case:** UC-20

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that a negative maximum price is rejected by server-side validation.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Maximum price: -25

**Test Steps:**
1. Request Item Search with `max_price=-25`.
2. Inspect the error heading and validation explanation.

**Expected Result:**
- The response displays `Invalid Price Range`.
- The response explains that the maximum must be a valid price with up to two decimal places.

**Actual Result:**
The `testNegativeMaximumPriceDisplaysValidationError` PHPUnit test found the expected heading and message.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-12: Reject Nonnumeric Minimum Price

**Use Case:** UC-20

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that server-side validation rejects a nonnumeric minimum value submitted in a request.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum price: abc

**Test Steps:**
1. Request Item Search with `min_price=abc`.
2. Inspect the error heading and validation explanation.

**Expected Result:**
- The response displays `Invalid Price Range`.
- The response explains that the minimum must be a valid price with up to two decimal places.

**Actual Result:**
The `testNonnumericMinimumPriceDisplaysValidationError` PHPUnit test found the expected heading and message.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-13: Reject Nonnumeric Maximum Price

**Use Case:** UC-20

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that server-side validation rejects a nonnumeric maximum value submitted in a request.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Maximum price: xyz

**Test Steps:**
1. Request Item Search with `max_price=xyz`.
2. Inspect the error heading and validation explanation.

**Expected Result:**
- The response displays `Invalid Price Range`.
- The response explains that the maximum must be a valid price with up to two decimal places.

**Actual Result:**
The `testNonnumericMaximumPriceDisplaysValidationError` PHPUnit test found the expected heading and message.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-14: Reject Prices With More Than Two Decimal Places

**Use Case:** UC-20

**Test Type:** Automated Functional / Boundary / Negative Testing

**Objective:**
Verify that price inputs exceeding two decimal places are rejected.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum price: 15.999

**Test Steps:**
1. Request Item Search with `min_price=15.999`.
2. Inspect the validation response.

**Expected Result:**
- The response displays `Invalid Price Range`.
- The response explains that the minimum price supports up to two decimal places.

**Actual Result:**
The `testMoreThanTwoDecimalPlacesDisplaysValidationError` PHPUnit test found the expected heading and message.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-15: Allow Zero as a Minimum Price

**Use Case:** UC-20

**Test Type:** Automated Functional

**Objective:**
Verify that a minimum price of zero is accepted and includes all eligible active listings.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum price: 0
- Maximum price: Blank

**Test Steps:**
1. Request Item Search with `min_price=0`.
2. Compare the result count against MySQL active listings with price at least zero.
3. Check for validation errors.

**Expected Result:**
- The request succeeds without a price validation error.
- The result count matches all eligible active listings.

**Actual Result:**
The `testZeroMinimumIncludesAllActiveListings` PHPUnit test verified the active count and absence of validation errors.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-16: Show No Results for a Price Above All Listings

**Use Case:** UC-20

**Test Type:** Automated Functional

**Objective:**
Verify that an excessively high valid minimum price produces the normal empty state.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum price: 999999999

**Test Steps:**
1. Request Item Search with the high minimum.
2. Inspect the message and result count.

**Expected Result:**
- The response displays `No listings found`.
- The result count is zero.

**Actual Result:**
The `testVeryHighMinimumDisplaysNoResults` PHPUnit test verified the empty state and zero result count.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-17: Do Not Filter Browse All When Price Inputs Are Blank

**Use Case:** UC-20

**Test Type:** Automated Functional

**Objective:**
Verify that leaving both price inputs blank preserves the full Browse All listing set.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Browse mode: all
- Minimum price: Blank
- Maximum price: Blank

**Test Steps:**
1. Request Browse All with empty `min_price` and `max_price`.
2. Compare the response count with the total active listing count in MySQL.
3. Check for price validation errors.

**Expected Result:**
- All active listings remain eligible.
- The displayed count matches the full active listing count.
- No price validation error appears.

**Actual Result:**
The `testBlankPriceFieldsDoNotRestrictBrowseAll` PHPUnit test verified the active count and absence of validation errors.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-18: Exclude Inactive Listings Even When Their Price Matches

**Use Case:** UC-20

**Test Type:** Automated Functional

**Objective:**
Verify that a price filter does not make an inactive listing visible.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- An inactive seeded listing and its stored price
- Minimum = maximum = the inactive listing price

**Test Steps:**
1. Retrieve an inactive listing’s name and price from MySQL.
2. Request Item Search filtered to its exact price.
3. Verify the inactive listing name is absent and the displayed count matches active items at that price.

**Expected Result:**
- The inactive listing remains excluded.
- Only active listings contribute to the displayed count.

**Actual Result:**
The `testInactiveListingIsExcludedEvenIfItsPriceMatches` PHPUnit test confirmed name exclusion and matched the active-price count.

**Status:** Pass

**Related Jira Defect:** None

### TC-20-19: Preserve Submitted Minimum and Maximum Values

**Use Case:** UC-20

**Test Type:** Automated Functional

**Objective:**
Verify that submitted price inputs remain populated after the results page reloads.

**Preconditions:**
- OmniBuy is running in Docker.
- The MySQL test database contains the required seeded listings and categories.

**Test Data:**
- Minimum price: 25.50
- Maximum price: 125.75

**Test Steps:**
1. Request Item Search with both price values.
2. Inspect the returned minimum and maximum HTML input elements.

**Expected Result:**
- The minimum input retains `25.50`.
- The maximum input retains `125.75`.

**Actual Result:**
The `testSubmittedPriceValuesRemainInFilterInputs` PHPUnit test verified both input values in the returned HTML.

**Status:** Pass

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
The automated test confirmed that the number of active
shipping listings returned by the search page matched
the corresponding MySQL count.

The Shipping radio button remained selected, and the
Meetup Distance filter was hidden.

Manual testing confirmed that only shipping listings
appeared in the search results.

**Status:** Pass

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
The automated test confirmed that the number of active
meetup listings returned by the search page matched
the corresponding MySQL count.

The Meetup radio button remained selected, and the
Meetup Distance filter was visible.

Manual testing confirmed that only meetup listings
appeared in the search results.

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
The automated test confirmed that leaving both delivery
methods unselected returned the full active listing count.

The result count matched the combined shipping and meetup
listing counts in MySQL.

Neither radio button was selected, and the Meetup Distance
filter remained visible.

Manual testing confirmed that both shipping and meetup
listings appeared together.

**Status:** Pass

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
The automated test verified that applying Shipping and
Good condition together returned the expected number
of active listings.

The result count matched the corresponding MySQL query.

Both the Shipping radio button and Good condition
checkbox remained selected.

Manual testing confirmed that only listings meeting
both filter requirements appeared.

**Status:** Pass

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
The automated test confirmed that removing the Shipping
filter restored the full active listing count.

Both delivery method radio buttons were unselected,
and the Meetup Distance filter was visible.

Manual testing confirmed that clicking Clear removed
the delivery restriction and restored both shipping
and meetup listings.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that filtering meetup listings within 10 miles of ZIP code 92831 returned the expected results. A temporary nearby meetup listing was created to verify that eligible listings were included. The returned listing IDs matched the MySQL distance query, confirming that listings outside the radius and shipping-only listings were excluded. The ZIP code, radius, and delivery method selections remained applied, and the page loaded without PHP or database errors. The temporary listing was removed after testing.

Manual testing was performed through the local OmniBuy website by selecting Meetup, entering ZIP code 92831, and choosing a 10-mile radius. The search results displayed the expected empty state because the existing seed data contained no eligible meetup listings within that distance. The selected filters remained applied, and the page displayed no PHP or database errors.

**Status:** Pass

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
The automated test verified that active listings with
unique item names appeared in ascending price order.

Manual testing confirmed that the marketplace displayed
listings from lowest to highest price.

The results were consistent with the corresponding
MySQL sorting query.

**Status:** pass

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
The automated test verified that active listings with
unique item names appeared in descending price order.

Manual testing confirmed that the marketplace displayed
listings from highest to lowest price.

The Price: High to Low option remained selected.
No PHP or database errors occurred.

**Status:** Pass

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
The automated test verified that active listings with
unique item names appeared in descending creation-date order.

The displayed order matched the MySQL query using
created_at DESC and listing_id DESC.

Manual testing confirmed that the newest listings
appeared first.

The Newest sorting option remained selected.

**Status:** Pass

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
The automated test verified that active listings with
unique item names appeared in ascending creation-date order.

The displayed order matched the MySQL query using
created_at ASC and listing_id ASC.

Manual testing confirmed that the oldest listings
appeared first.

The Oldest sorting option remained selected.

**Status:** Pass

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
The automated test confirmed that changing the sorting
option from Newest to Price Low to High preserved the
selected condition and delivery method filters.

The filtered result count remained unchanged and
matched the corresponding MySQL query.

The matching listings were displayed in ascending
price order.

Manual testing confirmed that changing the sorting
dropdown preserved both active filters.

**Status:** Pass

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
The automated test verified that active meetup listings
with valid coordinates appeared in ascending distance order.

Distances were calculated using MySQL's ST_Distance_Sphere()
function and compared against the displayed listing order.

The Distance: Near to Far sorting option remained selected.

Manual testing confirmed that the closest meetup listings
appeared first when a valid buyer ZIP code was provided.

**Status:** Pass

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
The automated test confirmed that providing an
unsupported sorting value did not cause a PHP
or database error.

The marketplace displayed active listings using
the default Newest sorting order.

The listing order matched the MySQL query using
created_at DESC and listing_id DESC.

Manual testing confirmed that the search page
handled the invalid sorting value successfully.

**Status:** Pass

**Related Jira Defect:** None


## UC-25: View Listing Detail
### TC-25-01: Open an Active Listing Detail Page

**Use Case:** UC-25

**Test Type:** Manual Functional / Automated Functional

**Objective:**
Verify that a buyer can open the detail page for an
active marketplace listing.

**Preconditions:**
- OmniBuy is running in Docker.
- An active listing exists.
- Listing Detail functionality is implemented.

**Test Data:**
- Known active listing ID

**Test Steps:**
1. Open the marketplace browse or search results.
2. Select a known active listing.
3. Open its detail page.
4. Examine the displayed listing information.

**Expected Result:**
- The detail page should load successfully.
- The displayed information should correspond to the
  selected listing.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-25-02: Display Correct Listing Information

**Use Case:** UC-25

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that Listing Detail displays the correct stored
information for the selected listing.

**Preconditions:**
- OmniBuy is running in Docker.
- A known active listing has complete listing data.

**Test Data:**
- Listing ID
- Item name
- Description
- Price
- Condition
- Category
- Fulfillment information
- Listing images, if available

**Test Steps:**
1. Retrieve the known listing record from the database.
2. Open the listing's detail page.
3. Compare displayed values with the stored listing data.
4. Examine any associated listing images.

**Expected Result:**
- Displayed listing values should match the database.
- Images should correspond to the selected listing.
- No unrelated listing information should appear.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-25-03: Handle Invalid Listing Identifier

**Use Case:** UC-25

**Test Type:** Automated Functional / Manual Negative Testing

**Objective:**
Verify that invalid or nonexistent listing identifiers
are handled safely.

**Preconditions:**
- OmniBuy is running in Docker.
- Listing Detail functionality is implemented.

**Test Data:**
- Non-numeric listing ID
- Nonexistent numeric listing ID

**Test Steps:**
1. Open the Listing Detail route with a non-numeric ID.
2. Examine the application's response.
3. Repeat with a numeric ID that does not exist.
4. Check for PHP or database errors.

**Expected Result:**
- Invalid listing identifiers should not cause SQL errors.
- Nonexistent listings should produce an appropriate
  unavailable or not-found state.
- No unrelated listing data should be displayed.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-25-04: Prevent Inactive Listing From Appearing as Available

**Use Case:** UC-25

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that an inactive listing is not presented to buyers
as an available marketplace item.

**Preconditions:**
- OmniBuy is running in Docker.
- A known hidden, sold, archived, or draft listing exists.

**Test Data:**
- Known inactive listing ID

**Test Steps:**
1. Attempt to open the inactive listing directly.
2. Examine the returned page or error state.
3. Verify the listing is not presented as actively available.
4. Compare the response with the listing's stored status.

**Expected Result:**
- The inactive listing should not be presented as an
  available marketplace purchase.
- The page should handle the unavailable listing safely.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None


### TC-25-05: Verify Listing Detail Uses the Correct Listing ID

**Use Case:** UC-25

**Test Type:** Automated Integration

**Objective:**
Verify that Listing Detail returns data only for the
requested listing ID.

**Preconditions:**
- An isolated test database is available.
- At least two listings with different data exist.

**Test Data:**
- Listing A with known values
- Listing B with different known values

**Test Steps:**
1. Open Listing A's detail page.
2. Record the displayed listing information.
3. Open Listing B's detail page.
4. Compare each page against its database record.
5. Check for data from the other listing.

**Expected Result:**
- Listing A should display only Listing A data.
- Listing B should display only Listing B data.
- Listing data should not be mixed between IDs.
- No PHP or database errors should occur.

**Actual Result:**
Not yet recorded.

**Status:** Not Run

**Related Jira Defect:** None
