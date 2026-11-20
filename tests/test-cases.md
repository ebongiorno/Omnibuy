# OmniBuy Individual Test Cases

## Testing Information

Project: OmniBuy
Testing Scope: Individual assigned use cases
Testing Framework: PHPUnit
Testing Environment: Docker, PHP, MySQL

## UC-07: Choose Delivery Method
### TC-07-01: Verify Seller-Defined Shipping Method at Checkout

**Use Case:** UC-07

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that checkout correctly identifies a shipping-only listing, displays the seller-defined shipping method, calculates shipping charges, and preserves the fulfillment method when the order is created.

**Preconditions:**
- OmniBuy is running in Docker.
- A buyer account is available.
- An active listing supports Shipping.
- Cart and checkout functionality are operational.

**Test Data:**
- Manual test listing: Classic Blastoise Pokemon Card
- Listing price: $150.00
- Shipping cost: $5.00
- Expected checkout total: $155.00
- Automated test: `testCheckoutCreatesTheOrderDecrementsStockAndEmptiesTheCart()`
- Automated test listing price: $20.00
- Automated test shipping cost: $5.00

**Test Steps:**
1. Log into OmniBuy as a buyer.
2. Add a shipping-only listing to the shopping cart.
3. Proceed to checkout.
4. Verify that Shipping appears beneath the listing name in the order summary.
5. Verify that the appropriate shipping charge is included in the order total.
6. Verify that the checkout displays the shipping address section.
7. Execute the automated checkout integration test using PHPUnit.
8. Verify that the completed test order stores `shipping` in `order_items.fulfillment_type`.
9. Verify that the correct shipping cost is stored with the order item.

**Expected Result:**
- Checkout displays Shipping as the listing's required fulfillment method.
- The buyer is not offered an unsupported alternative delivery method.
- The correct shipping charge is included in the checkout total.
- Shipping address information is required for the order.
- The completed order item stores `shipping` as its fulfillment type.
- The correct shipping cost is preserved.
- No PHP or database errors occur.

**Actual Result:**
Manual testing passed successfully. The Classic Blastoise Pokemon Card appeared in the checkout order summary with Shipping displayed beneath the listing name. The subtotal was $150.00, the shipping charge was $5.00, and the total was correctly calculated as $155.00.

The checkout displayed the billing and shipping address sections as expected.

Automated integration testing also passed successfully. The existing `testCheckoutCreatesTheOrderDecrementsStockAndEmptiesTheCart()` test was extended with assertions verifying that the saved order item contained `shipping` as its fulfillment type and `5.00` as its shipping cost.

Both new assertions passed. The complete `CartCheckoutTest.php` test file also passed during subsequent regression testing.

These results confirm that shipping-only listings retain their seller-defined fulfillment method during checkout and order creation.

**Status:** Pass

**Related Jira Defect:** None


### TC-07-02: Verify Seller-Defined Local Meetup Method at Checkout

**Use Case:** UC-07

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that checkout correctly identifies a meetup-only listing, displays the seller-defined Local Meetup method, excludes shipping charges, and preserves the meetup fulfillment method when the order is created.

**Preconditions:**
- OmniBuy is running in Docker.
- A buyer account is available.
- An active listing supports Local Meetup.
- Cart and checkout functionality are operational.

**Test Data:**
- Manual test listing: Power Rangers Lightning Collection Green Ranger
- Listing price: $46.00
- Shipping cost: $0.00
- Expected checkout total: $46.00
- Automated test: `testMeetupOnlyOrderHasNoShippingAddress()`
- Automated test fulfillment type: `meetup`
- Automated test shipping cost: `0.00`

**Test Steps:**
1. Log into OmniBuy as a buyer.
2. Add a meetup-only listing to the shopping cart.
3. Proceed to checkout.
4. Verify that Local Meetup appears beneath the listing name in the order summary.
5. Verify that no shipping charge is included in the order total.
6. Verify that the checkout does not require a shipping address.
7. Verify that meetup instructions are displayed to the buyer.
8. Execute the automated meetup checkout integration test using PHPUnit.
9. Verify that the completed test order does not contain a shipping address.
10. Verify that the saved order item contains `meetup` in `order_items.fulfillment_type`.
11. Verify that the saved order item contains `0.00` as its shipping cost.

**Expected Result:**
- Checkout displays Local Meetup as the listing's required fulfillment method.
- The buyer is not offered an unsupported alternative delivery method.
- No shipping charges are applied.
- The shipping address section is not required or displayed for a meetup-only order.
- Billing information remains available as required by checkout.
- Meetup instructions explain how buyers should coordinate with sellers.
- The completed order item stores `meetup` as its fulfillment type.
- The order and order item store a shipping cost of `0.00`.
- No shipping address is associated with the completed order.
- No PHP or database errors occur.

**Actual Result:**
Manual testing passed successfully. The Power Rangers Lightning Collection Green Ranger listing appeared in the checkout order summary with Local Meetup displayed beneath the listing name. The subtotal was $46.00, the shipping charge was $0.00, and the total was correctly calculated as $46.00.

The checkout displayed the billing address section but did not display the shipping address section. The page also provided instructions explaining that meetup-only purchases do not require shipping addresses and that buyers should coordinate pickup arrangements with sellers through Messages.

Automated integration testing also passed successfully. The existing `testMeetupOnlyOrderHasNoShippingAddress()` test verified that the completed order did not contain a shipping address and that the order's shipping cost was `0.00`.

The test was extended with additional assertions verifying that exactly one order item was created, its fulfillment type was `meetup`, and its shipping cost was `0.00`.

All three new assertions passed. The complete `CartCheckoutTest.php` test file also passed during subsequent regression testing.

These results confirm that meetup-only listings retain their seller-defined fulfillment method during checkout and order creation without requiring shipping information or applying shipping charges.

**Status:** Pass

**Related Jira Defect:** None


### TC-07-03: Verify Mixed-Delivery Checkout

**Use Case:** UC-07

**Test Type:** Manual Functional / Automated Integration

**Objective:**
Verify that OmniBuy correctly handles a checkout containing multiple listings with different seller-defined delivery methods. Confirm that each listing displays its required fulfillment method, shipping charges are calculated correctly, and both delivery methods are preserved when the order is created.

**Preconditions:**
- OmniBuy is running in Docker.
- A buyer account is available.
- At least one active shipping-only listing exists.
- At least one active meetup-only listing exists.
- Cart and checkout functionality are operational.
- The buyer can add multiple listings to the same cart.

**Test Data:**

Manual Test:
- Shipping listing: Classic Blastoise Pokemon Card
- Shipping listing price: $150.00
- Shipping charge: $5.00
- Meetup listing: Power Rangers Lightning Collection Green Ranger
- Meetup listing price: $46.00
- Meetup shipping charge: $0.00
- Expected subtotal: $196.00
- Expected shipping total: $5.00
- Expected order total: $201.00

Automated Test:
- Test method: `testMixedDeliveryOrderSavesBothFulfillmentTypes()`
- Shipping listing price: $20.00
- Meetup listing price: $40.00
- Shipping charge: $5.00
- Expected subtotal: $60.00
- Expected order total: $65.00

**Test Steps:**
1. Log into OmniBuy as a buyer.
2. Add a shipping-only listing to the shopping cart.
3. Add a meetup-only listing to the same cart.
4. Proceed to checkout.
5. Verify that both listings appear in the order summary.
6. Verify that Shipping appears beneath the shipping-only listing.
7. Verify that Local Meetup appears beneath the meetup-only listing.
8. Confirm that the shipping charge applies only to the shipping listing.
9. Verify that the subtotal, shipping cost, and total are calculated correctly.
10. Verify that the shipping address section is displayed because the cart contains a shipping item.
11. Execute the automated integration test `testMixedDeliveryOrderSavesBothFulfillmentTypes()` using PHPUnit.
12. Verify that both order items are associated with the same order.
13. Verify that the shipping listing stores `shipping` in `order_items.fulfillment_type`.
14. Verify that the meetup listing stores `meetup` in `order_items.fulfillment_type`.
15. Verify that each order item retains its appropriate shipping charge.
16. Verify that the completed order stores the correct subtotal, shipping cost, total amount, and shipping address reference.

**Expected Result:**
- Both shipping and meetup listings can be included in the same checkout.
- Each listing displays its seller-defined fulfillment method.
- The buyer cannot substitute an unsupported delivery method.
- Shipping charges are applied only to shipping listings.
- Meetup listings do not generate shipping charges.
- The order summary correctly combines the prices and applicable shipping costs.
- The shipping address section is displayed when at least one listing requires shipping.
- Both purchased items are saved under the same order.
- Each saved order item retains its correct fulfillment type.
- The completed order contains a shipping address when shipping is required.
- No PHP or database errors occur.

**Actual Result:**
Manual testing passed successfully. The Classic Blastoise Pokemon Card and Power Rangers Lightning Collection Green Ranger listings were added to the same shopping cart and displayed together during checkout.

The Blastoise card correctly displayed Shipping beneath its listing name, while the Green Ranger listing displayed Local Meetup. Both labels used the same styling and appeared beneath their corresponding listing names.

The order summary correctly calculated a subtotal of $196.00, a shipping charge of $5.00, and a total of $201.00. The shipping address section was displayed because the Blastoise card required shipping.

Automated integration testing also passed successfully. The new `testMixedDeliveryOrderSavesBothFulfillmentTypes()` test created temporary shipping and meetup listings, added both to the buyer's cart, and completed checkout within the isolated test transaction.

The test verified that exactly two order items were created under the same order ID. The shipping listing retained `shipping` as its fulfillment type with a stored shipping charge of `5.00`. The meetup listing retained `meetup` with a stored shipping charge of `0.00`.

Additional assertions confirmed that the completed order recorded a subtotal of `60.00`, a shipping charge of `5.00`, and a total amount of `65.00`. The order also contained a non-null shipping address reference.

All assertions passed. The complete `CartCheckoutTest.php` test file also passed during subsequent regression testing.

These results confirm that OmniBuy correctly supports mixed-delivery purchases while preserving each seller's required fulfillment method and applying shipping charges accurately.

**Status:** Pass

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
The automated PHPUnit test passed successfully. A temporary user, category, and active listing were created, and the user was authenticated as the listing owner. The My Listings page returned HTTP 200 and correctly displayed the newly created listing.

The test confirmed that the listing contained a View Analytics link with the correct listing ID. Accessing this link returned HTTP 200 and displayed the Listing Analytics page with the correct listing name and expected price of $25.00.

The analytics page successfully rendered all six metric-label elements. All assertions passed, confirming that an authenticated listing owner can access their listing analytics and view the corresponding information.

**Status:** Pass

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
The PHPUnit test passed successfully. Controlled analytics records were created for a temporary listing, including three listing views from two unique viewers, two recent views, two wishlist saves, one conversation, and two messages.

The authenticated listing owner successfully accessed the analytics page. All six displayed metrics matched the expected database values. The test completed without PHP or database errors after correcting the test-data cleanup order.

**Status:** Pass

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
The PHPUnit test passed successfully. Two temporary listings were created under the same authenticated user, with different analytics activity recorded for each listing.

Both analytics pages returned HTTP 200 and displayed the correct listing information without displaying the other listing's details. Listing A correctly reported three total views, two unique viewers, two recent views, two wishlist saves, one conversation, and two messages. Listing B correctly reported one total view, one unique viewer, one recent view, and zero wishlist saves, conversations, and messages.

All assertions passed, confirming that analytics data is correctly associated with its corresponding listing and does not include activity from other listings.

**Status:** Pass

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
The PHPUnit test passed successfully. A temporary listing was created without any recorded analytics activity, and the authenticated listing owner successfully accessed its analytics page.

The page returned HTTP 200 and correctly displayed the listing information. All six analytics metrics—Total Views, Unique Viewers, Views in Last 7 Days, Wishlist Saves, Total Conversations, and Total Messages—displayed a value of zero.

All assertions passed, confirming that listings without recorded activity display accurate zero-valued metrics without PHP errors, database errors, or missing analytics information.

**Status:** Pass

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
The PHPUnit test passed successfully. Two temporary user accounts were created, with User A owning a listing containing recorded analytics activity.

When User B attempted to access User A's listing analytics, the application returned HTTP 404. The unauthorized response did not disclose the listing name or any analytics metrics.

A separate authenticated session was established for User A, who successfully accessed the same listing's analytics page with HTTP 200. The correct listing information and recorded view metrics were displayed.

All assertions passed, confirming that analytics access is restricted to the listing owner without preventing authorized access.

**Status:** Pass

**Related Jira Defect:** None


### TC-11-06: Analytics Page Requires Login

**Use Case:** UC-11

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that unauthenticated users are redirected to the login page when attempting to access listing analytics.

**Preconditions:**
- OmniBuy is running in Docker.
- Listing analytics functionality is implemented.
- The user is not authenticated.

**Test Data:**
- Listing ID: 1
- Unauthenticated HTTP request

**Test Steps:**
1. Send an unauthenticated GET request to the listing analytics page.
2. Verify that the response returns HTTP 302.
3. Verify that the redirect points to the login page.

**Expected Result:**
- Unauthenticated users should be redirected to the login page.
- The response should return HTTP 302.
- The redirect URL should include the original destination.

**Actual Result:**
The PHPUnit test passed. The application returned HTTP 302 and redirected to the login page as expected.

**Status:** Pass

**Related Jira Defect:** None


### TC-11-07: Reject Invalid Listing IDs

**Use Case:** UC-11

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that the listing analytics page rejects missing, malformed, and nonpositive listing IDs without exposing analytics information.

**Preconditions:**
- OmniBuy is running in Docker.
- Listing analytics functionality is implemented.
- An authenticated user account is available.

**Test Data:**
- Missing listing ID
- Zero listing ID
- Negative listing ID
- Non-numeric listing ID
- Decimal listing ID

**Test Steps:**
1. Create a temporary user and authenticate.
2. Attempt to access listing analytics using each invalid listing ID.
3. Verify that each request returns HTTP 400.
4. Verify that the appropriate validation error message is displayed.
5. Confirm that all invalid inputs are rejected.

**Expected Result:**
- All five invalid listing ID requests should return HTTP 400.
- The application should display an invalid listing ID error.
- No listing analytics information should be disclosed.
- No PHP or database errors should occur.

**Actual Result:**
The PHPUnit test passed successfully. An authenticated test user attempted to access the listing analytics page using five invalid listing ID inputs: a missing ID, zero, a negative integer, a non-numeric value, and a decimal value.

All five requests returned HTTP 400 and displayed the expected "Invalid listing ID." validation message. All assertions passed, confirming that the application correctly rejects malformed and invalid listing identifiers.

**Status:** Pass

**Related Jira Defect:** None


### TC-11-08: Reject Nonexistent Listing IDs

**Use Case:** UC-11

**Test Type:** Automated Functional / Negative Testing

**Objective:**
Verify that the listing analytics page rejects valid positive listing IDs that do not correspond to existing database records.

**Preconditions:**
- OmniBuy is running in Docker.
- Listing analytics functionality is implemented.
- An authenticated user account is available.

**Test Data:**
- Authenticated temporary user
- Positive listing ID not present in the database

**Test Steps:**
1. Create and authenticate a temporary user.
2. Generate and verify a nonexistent positive listing ID.
3. Attempt to access analytics using the nonexistent listing ID.
4. Verify that the response returns HTTP 404.
5. Verify that the appropriate error message is displayed and no analytics cards are returned.

**Expected Result:**
- The application should return HTTP 404.
- The response should display the listing not found or unavailable message.
- No listing analytics information should be displayed.
- No PHP or database errors should occur.

**Actual Result:**
The PHPUnit test passed successfully. A temporary user was created and authenticated, and a valid positive listing ID was generated and confirmed not to exist in the database.

When the authenticated user attempted to access analytics for the nonexistent listing, the application correctly returned HTTP 404 and displayed the expected "Listing not found or unavailable." error message. No analytics metric cards were disclosed.

All assertions passed, confirming that the application handles nonexistent listing IDs without exposing analytics information.

**Status:** Pass

**Related Jira Defect:** None


### TC-11-09: View Analytics for a Sold Listing

**Use Case:** UC-11

**Test Type:** Automated Functional / Regression Testing

**Objective:**
Verify that a listing owner can continue accessing analytics after marking a listing as sold and that previously recorded analytics data remains available.

**Preconditions:**
- OmniBuy is running in Docker.
- Listing analytics functionality is implemented.
- An authenticated listing owner is available.
- A listing can have its status changed to sold.

**Test Data:**
- Temporary listing owner and viewer
- Temporary listing with one recorded view
- Listing status: Sold

**Test Steps:**
1. Create a temporary listing and record one view.
2. Change the listing status to sold.
3. Authenticate as the listing owner.
4. Verify that My Listings displays the sold listing and its View Analytics link.
5. Access the listing analytics page.
6. Verify HTTP 200, the correct listing information, and sold status.
7. Verify that the recorded view remains in the analytics metrics.

**Expected Result:**
- The sold listing should remain accessible through My Listings.
- The View Analytics link should remain available.
- The analytics page should return HTTP 200 and display Sold as the listing status.
- Previously recorded analytics should be preserved.
- Metrics without recorded activity should display zero.
- No PHP or database errors should occur.

**Actual Result:**
The PHPUnit test passed successfully. A temporary listing was created with one recorded view before its status was changed from active to sold.

The authenticated listing owner successfully accessed My Listings, where the sold listing and its View Analytics link remained available. The analytics page returned HTTP 200 and displayed the correct listing information with a status of Sold.

The previously recorded view was preserved, with Total Views, Unique Viewers, and Views in Last 7 Days each displaying one. Wishlist Saves, Total Conversations, and Total Messages correctly displayed zero.

All assertions passed, confirming that listing analytics remain accessible and accurate after a listing has been marked as sold.

**Status:** Pass

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
The functional test successfully created a temporary seller account and an active listing, then authenticated as the listing owner. The My Listings page displayed the listing and its Mark as Sold button. After submitting the Mark as Sold request with a valid CSRF token, the application returned the expected redirect, and the database confirmed that the listing status changed from active to sold. The updated page continued displaying the listing, showed the success confirmation, and no longer displayed the Mark as Sold button. All assertions passed.

**Status:** Pass

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
The functional test successfully created a temporary seller account and two active listings belonging to that seller. The initial database records confirmed that both listings had an active status. After authenticating as the listing owner and submitting a valid Mark as Sold request, the application returned the expected HTTP 303 redirect. A subsequent database query confirmed that the targeted listing's status changed to sold, while its listing ID and seller ownership remained unchanged. The unrelated listing retained its active status, confirming that the update affected only the intended record. All assertions passed, both when TC-14-02 was executed individually and when it was run together with TC-14-01.

**Status:** Pass

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
The functional test successfully created a temporary seller account and two active listings containing a unique search term. Both listings initially appeared in Browse All and keyword search results. After authenticating as the listing owner and marking one listing as sold, the database confirmed that its status changed to sold while the other listing remained active. Subsequent searches confirmed that the sold listing no longer appeared in either Browse All or keyword search results, while the active listing remained visible and accessible through its listing link. All assertions passed.

**Status:** Pass

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
The functional test successfully created a temporary seller account and an active listing, then authenticated as the listing owner and submitted a valid Mark as Sold request. The application returned the expected HTTP 303 redirect, and the database confirmed that the listing remained stored with its original listing ID, seller ownership, and item name while its status changed to sold. When the seller accessed My Listings again without the success-message URL parameter, the sold listing remained visible with its updated status. The Mark as Sold button was no longer displayed. All assertions passed.

**Status:** Pass

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
The functional test successfully created two temporary user accounts and an active listing belonging to User A. After authenticating as User B, the application received a Mark as Sold request targeting User A's listing. The request was rejected with the expected HTTP 404 response. A subsequent database query confirmed that the listing remained active and its ownership was unchanged. The test also verified that User A's listing was not displayed on User B's My Listings page. All assertions passed, confirming that users cannot modify listings belonging to other accounts.

**Status:** Pass

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
The functional test successfully created a temporary seller account and two active listings to verify repeated and invalid status change requests. After authenticating as the listing owner, the first listing was successfully marked as sold, returning the expected HTTP 303 redirect. A subsequent attempt to mark the same listing as sold was rejected with HTTP 404, and its status remained unchanged. The application also correctly rejected an invalid listing ID with HTTP 400 and requests containing missing or incorrect CSRF tokens with HTTP 403. A GET request attempting to change the listing status did not modify the database. Final verification confirmed that the first listing remained sold while the second listing remained active. All assertions passed, both individually and when executed alongside the other five UC-14 tests.

**Status:** Pass

**Related Jira Defect:** None


### TC-14-07: Prevent Unauthenticated Listing Status Changes

**Use Case:** UC-14

**Test Type:** Automated Functional / Security Testing

**Objective:**

Verify that an unauthenticated user cannot mark an existing listing as sold or modify its database record.

**Preconditions:**
- The OmniBuy application and test database are available.
- A temporary user account and active listing exist.
- The HTTP client has not authenticated as a user.

**Test Data:**
- Temporary seller account
- Active listing owned by the temporary seller
- Unauthenticated HTTP session
- Valid guest-session CSRF token
- Mark as Sold POST request

**Test Steps:**
1. Create a temporary seller account and active listing.
2. Verify that the listing initially has an `active` status.
3. Create a new HTTP client without logging in.
4. Obtain a valid CSRF token from the login page.
5. Submit a Mark as Sold POST request for the listing.
6. Verify that the request redirects to the login page.
7. Query the database to confirm the listing remains active and its ownership is unchanged.
8. Attempt to access My Listings without authentication and verify that access is redirected to login.

**Expected Result:**

The application should reject unauthenticated attempts to mark a listing as sold by redirecting the user to the login page. The listing must remain active, its ownership and information must remain unchanged, and unauthenticated users must not be allowed to access My Listings.

**Actual Result:**
The functional test successfully created a temporary seller account and an active listing, then attempted to mark the listing as sold using an unauthenticated HTTP session with a valid CSRF token. The application rejected the request and returned the expected HTTP 302 redirect to the login page. A subsequent database query confirmed that the listing remained active, with its original ownership and item information unchanged. The test also verified that an unauthenticated attempt to access the My Listings page resulted in a redirect to login. All assertions passed, confirming that authentication is required to modify listing statuses.

**Status:** Pass

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
The functional test successfully created a temporary seller account and an active listing with no previous renewal timestamp. After authenticating as the listing owner, the test confirmed that the listing appeared on the My Listings page with the Renew Listing button available. The seller submitted a valid renewal request, and the application returned the expected HTTP 303 redirect to the renewal confirmation page. A subsequent database query confirmed that the listing's renewed_at field was populated while its original creation date, listing status, ownership, and item name remained unchanged. The test also verified that the application displayed the renewal success message and Last Renewed field. All assertions passed.

**Status:** Pass

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
The functional test successfully created a temporary seller account and two active listings belonging to that seller. The target listing was assigned an original creation date 30 days in the past and an existing renewal timestamp seven days in the past. After authenticating as the listing owner and submitting a valid Renew Listing request, the application returned the expected HTTP 303 redirect. A subsequent database query confirmed that the target listing's renewed_at timestamp had changed to a more recent value within the recorded database request window. The listing's original creation date, listing ID, ownership, name, and active status remained unchanged. The unrelated control listing also retained all its original database values, confirming that only the intended listing was updated. All assertions passed, both when TC-15-02 was executed individually and when it was run together with TC-15-01.

**Status:** Pass

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
The functional test successfully created a temporary seller account and two active listings containing a unique search term. The listings were assigned different creation dates, with one listing created 30 days earlier and the other one day earlier. Initial keyword search results using Newest sorting confirmed that the more recently created listing appeared first. After authenticating as the listing owner and renewing the older listing, the application returned the expected HTTP 303 redirect, and the database confirmed that the older listing received a renewal timestamp while both listings remained active. Subsequent keyword search results confirmed that the renewed listing moved ahead of the newer listing, demonstrating that Newest sorting correctly uses the renewal timestamp when available. All assertions passed, both individually and when TC-15-03 was executed alongside TC-15-01 and TC-15-02.

**Status:** Pass

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
The functional test successfully created two temporary seller accounts and an active listing belonging to Seller A. After confirming the listing's original ownership, active status, and empty renewal timestamp, the test authenticated as Seller B and submitted a renewal request targeting Seller A's listing. The application rejected the unauthorized request with the expected HTTP 404 response. A subsequent database query confirmed that the listing remained active, its ownership was unchanged, and its renewed_at timestamp remained NULL. All retrieved listing fields matched their original values. The test also verified that Seller A's listing was not displayed on Seller B's My Listings page. All assertions passed, both individually and when executed alongside TC-15-01 through TC-15-03.

**Status:** Pass

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
The functional test successfully created a temporary seller account and multiple listings to verify that ineligible listings could not be renewed. After authenticating as the listing owner, the test attempted to renew listings with sold, archived, hidden, and draft statuses. Each renewal request was rejected with the expected HTTP 404 response. Subsequent database queries confirmed that every listing retained its original status, ownership, and other retrieved information, while its renewed_at timestamp remained NULL. An unrelated active control listing also remained unchanged, confirming that the rejected requests did not affect other records. All assertions passed, both when TC-15-05 was executed individually and when it was run alongside TC-15-01 through TC-15-04.

**Status:** Pass

**Related Jira Defect:** None


### TC-15-06: Prevent Unauthenticated Listing Renewal

**Use Case:** UC-15

**Test Type:** Automated Functional / Security Testing

**Objective:**

Verify that an unauthenticated user cannot renew an existing active listing or modify its database record, even when submitting a valid CSRF token.

**Preconditions:**
- The OmniBuy application and test database are available.
- A temporary seller account and active listing exist.
- The listing has not previously been renewed.
- A separate HTTP client is available without an authenticated user session.

**Test Data:**
- Temporary seller account
- Active listing belonging to the temporary seller
- Known listing ID
- Unauthenticated HTTP session
- Valid guest-session CSRF token
- Renew Listing POST request

**Test Steps:**
1. Create a temporary seller account and active listing.
2. Retrieve the listing's initial database record and verify that its status is `active`, its `renewed_at` timestamp is `NULL`, and its ownership is correct.
3. Create a separate HTTP client without authenticating as any user.
4. Visit the login page and obtain a valid CSRF token for the unauthenticated session.
5. Submit a Renew Listing POST request targeting the active listing.
6. Verify that the application rejects the request and redirects the unauthenticated visitor to the login page.
7. Retrieve the listing's database record and compare it against the original values.
8. Verify that the listing remains active, its renewal timestamp remains `NULL`, and its ownership and other information are unchanged.
9. Attempt to access the My Listings page without authentication and verify that the visitor is redirected to login.

**Expected Result:**
- The application should reject unauthenticated renewal requests with an HTTP 302 redirect to the login page.
- The listing's `renewed_at` timestamp should remain `NULL`.
- The listing should retain its original status, ownership, creation date, and other stored information.
- Unauthenticated visitors should not be permitted to access the My Listings page.
- No unauthorized database modifications should occur.

**Actual Result:**
The functional test successfully created a temporary seller account and an active listing with no previous renewal timestamp. The test then established a separate unauthenticated HTTP session and obtained a valid guest-session CSRF token. When the unauthenticated visitor submitted a renewal request, the application rejected the action and returned the expected HTTP 302 redirect to the login page. A subsequent database query confirmed that the listing remained active, its `renewed_at` timestamp remained `NULL`, and its original ownership and other retrieved information were unchanged. The test also verified that an unauthenticated visitor attempting to access the My Listings page was redirected to login. All assertions passed, confirming that authentication is required to renew a listing.

**Status:** Pass

**Related Jira Defect:** None

### TC-15-07: Handle Invalid Renewal Requests

**Use Case:** UC-15

**Test Type:** Automated Functional / Security Testing / Negative Testing

**Objective:**

Verify that the application rejects invalid or malformed listing renewal requests, including invalid listing IDs, missing or incorrect CSRF tokens, unsupported actions, and attempts to perform renewal through GET requests, without modifying listing records.

**Preconditions:**
- The OmniBuy application and test database are available.
- A temporary seller account and active listing exist.
- The listing has not previously been renewed.
- The seller is authenticated.
- A valid CSRF token is available for the authenticated session.

**Test Data:**
- Temporary authenticated seller account
- Active listing belonging to the seller
- Known valid listing ID
- Invalid listing ID (`invalid-id`)
- Valid CSRF token
- Missing CSRF token
- Incorrect CSRF token (`invalid-security-token`)
- Unsupported action (`invalid_action`)
- GET request containing renewal parameters

**Test Steps:**
1. Create a temporary seller account and active listing.
2. Retrieve the listing's original database record and verify that its status is `active` and its `renewed_at` timestamp is `NULL`.
3. Authenticate as the listing owner and obtain a valid CSRF token.
4. Submit a renewal request containing an invalid listing ID and verify that the application returns HTTP 400.
5. Query the database to confirm that the listing remains unchanged.
6. Submit a renewal request without a CSRF token and verify that the application returns HTTP 403.
7. Confirm that the listing's database record remains unchanged.
8. Submit a renewal request with an incorrect CSRF token and verify that the application returns HTTP 403.
9. Confirm that the listing's database record remains unchanged.
10. Submit a POST request containing an unsupported action and verify that the application returns HTTP 400.
11. Confirm that the listing's database record remains unchanged.
12. Attempt to renew the listing through a GET request and verify that the application returns HTTP 200 without processing the renewal.
13. Retrieve the final database record and compare it against the original values.
14. Verify that the listing remains active, its `renewed_at` timestamp remains `NULL`, its ownership is unchanged, and no unauthorized modifications occurred.

**Expected Result:**
- Requests containing an invalid listing ID should be rejected with HTTP 400.
- Requests containing missing or incorrect CSRF tokens should be rejected with HTTP 403.
- Requests containing unsupported actions should be rejected with HTTP 400.
- GET requests should not trigger listing renewal or modify database records.
- The listing should remain `active` throughout the test.
- The listing's `renewed_at` timestamp should remain `NULL`.
- The listing's ownership, creation date, and other stored information should remain unchanged.
- No invalid renewal request should modify the listing or cause unintended database changes.

**Actual Result:**
The functional test successfully created a temporary seller account and an active listing with no previous renewal timestamp. After authenticating as the listing owner, the test submitted several invalid renewal requests to verify the application's input validation and security protections. A request containing an invalid listing ID was rejected with HTTP 400, while requests containing missing or incorrect CSRF tokens were rejected with HTTP 403. An unsupported action was also rejected with HTTP 400. The test additionally confirmed that attempting to renew a listing through a GET request returned HTTP 200 without processing the renewal. Database queries performed after each invalid request verified that the listing remained unchanged. Final verification confirmed that the listing retained its active status, original ownership, creation date, and other stored information, while its renewed_at timestamp remained NULL. All assertions passed.

**Status:** Pass

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
Marketplace Browse: `Browse feed displays active seeded listing` passed; the browse feed displayed an eligible seeded listing.

**Status:** Pass (automated and manual)

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
Marketplace Browse: `Browse feed excludes inactive listings` passed; inactive listings were excluded from the displayed homepage feed.

**Status:** Pass (automated and manual)

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
Marketplace Browse: `Browse all displays active seeded listing` passed; Browse All returned an active listing without a keyword.

**Status:** Pass (automated and manual)

**Related Jira Defect:** None


### TC-16-04: Handle Browse Feed With No Eligible Listings

**Use Case:** UC-16

**Test Type:** Manual Functional / Negative Testing (automated coverage not established)

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
Manual testing was completed on the OmniBuy website for a homepage feed with no eligible listings. The empty-feed behavior was observed to work as expected, and the test passed. This is a user-reported manual result; the supplied PHPUnit suite does not establish a dedicated empty-homepage test.

**Status:** Pass (manual; dedicated automated test not confirmed)

**Related Jira Defect:** None


**Personalized recommendation PHPUnit source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Execution evidence:** User-confirmed successful targeted recommendation tests and complete PHPUnit test suite. These tests validate the recommendation helper; homepage rendering should be verified separately.

### TC-16-05: Calculate Category Scores From Search and Listing Views

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testCategoryScoresCombineViewAndSearchWeights`

**Objective:**
Verify that item searches contribute two points and listing views contribute three points to category interests.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
A temporary buyer, active seller, category, searchable listing, two recorded listing views, and one matching item search.

**Test Steps:**
1. Create a buyer, seller, and active listing in a temporary category.
2. Insert two views of the listing and one matching item search for the buyer.
3. Call `recommendationCategoryScores()` and inspect the category score.

**Expected Result:**
- The category receives six points for views and two for the search.
- The combined category score is exactly eight.

**Actual Result:**
The category score was asserted to equal eight (3 + 3 + 2). The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-06: Count Each Search Once Per Matching Parent Category

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testOneSearchContributesOncePerMatchingRootCategory`

**Objective:**
Verify that one keyword search does not gain additional weight merely because multiple listings in the same category match.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
A buyer, two active listings with a shared unique keyword in one category, and one recorded item search.

**Test Steps:**
1. Create two active listings in the same category sharing a unique search keyword.
2. Record one matching item search for the buyer.
3. Calculate scores for the buyer and inspect the matched category.

**Expected Result:**
- The single search contributes exactly two points to that category.
- The number of matching listings does not multiply the search weight.

**Actual Result:**
One search matched two listings, and the test asserted exactly two points for their category. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-07: Group Subcategory Activity Under the Parent Category

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testSubcategoryActivityAccumulatesUnderParentCategory`

**Objective:**
Verify that searches and views of a subcategory listing contribute to its parent-category score.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
A parent category, child category, active listing in the child, one view, and one matching search.

**Test Steps:**
1. Create a parent category and its child category.
2. Record a buyer view and matching item search for the child-category listing.
3. Calculate category scores and inspect both parent and child IDs.

**Expected Result:**
- The parent category receives five points.
- No separate score entry remains for the child category.

**Actual Result:**
The parent received five points (3 + 2) and the child had no independent score entry. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-08: Keep Recommendation Activity Separate by User

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testCategoryScoresAreIsolatedPerUser`

**Objective:**
Verify that one buyer's history does not influence another buyer's category scores.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
Two buyers; two categories; one viewed listing per buyer; invalid user ID 0.

**Test Steps:**
1. Record a view in Category A for Buyer A and a view in Category B for Buyer B.
2. Calculate category scores separately for both buyers.
3. Verify a nonpositive user ID produces no scores.

**Expected Result:**
- Buyer A receives only Category A: 3 points.
- Buyer B receives only Category B: 3 points.
- User ID 0 returns an empty score array.

**Actual Result:**
The test verified independent per-user score arrays and an empty result for user ID 0. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-09: Ignore Expired and Non-Recommendation Activity

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testIrrelevantOrExpiredActivityDoesNotIncreaseScores`

**Objective:**
Verify that outdated actions, seller-profile searches, and views of the user's own listings are excluded from interest scoring.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
An authenticated seller/buyer; search and view events older than 90 days; seller-profile search; own-listing view.

**Test Steps:**
1. Create controlled item and seller-profile search records, including activity older than 90 days.
2. Insert an old view of another seller's listing and a current view of an owned listing.
3. Calculate the user's category interests.

**Expected Result:**
- Expired search and view records do not add points.
- Seller-profile searches and own-listing views do not add points.
- The resulting category score array is empty.

**Actual Result:**
The test confirmed no scores were assigned from expired, seller-profile, or own-listing activity. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-10: Exclude Unavailable Listing and Inactive Seller Activity From Scores

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testInactiveListingAndSellerActivityIsExcludedFromScores`

**Objective:**
Verify that previously recorded signals stop contributing when their listing becomes sold or their seller becomes inactive.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
One active listing with search/view history; statuses `sold` and seller `suspended`.

**Test Steps:**
1. Record an item search and listing view for an active seller's listing.
2. Mark the listing sold and calculate category interests.
3. Reactivate the listing, suspend the seller, and recalculate.

**Expected Result:**
- Sold listings do not contribute category points.
- Listings owned by suspended sellers do not contribute category points.

**Actual Result:**
The test asserted empty category scores after selling the listing and again after suspending its seller. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-11: Select Higher-Scoring Categories Before Sorting Personalized Results

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testRecentFeedPrioritizesHigherScoringCategories`

**Objective:**
Verify that a signed-in user with meaningful category-interest scores receives the highest-ranked eligible recommendations as the selected candidate set, before the user-selected display sort is applied.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Temporary buyer, seller, and listing fixtures are available with transaction rollback.

**Test Data:**
Three categories with strong, weak, and zero interest; eligible listings with controlled timestamps.

**Test Steps:**
1. Record two eligible views in the strong category and one in the weak category.
2. Create eligible listings in those categories and an unrelated category.
3. Request the personalized feed with a limit that makes candidate selection observable.
4. Verify higher-scoring categories are selected before unscored fallback candidates, regardless of the final Newest display order.

**Expected Result:**
- The algorithm selects eligible candidates by category-interest score descending, using effective recency and listing ID as candidate-selection tie-breakers.
- Unscored eligible items fill remaining slots if needed.
- The final `recent` display order may place a newer lower-scored recommendation before an older higher-scored recommendation; that does not change the selected set.

**Actual Result:**
Personalized Recommendations: `Recent feed prioritizes higher scoring categories` passed in the complete PHPUnit suite. The test verifies category-based recommendation ranking; separate manual testing confirmed changes in displayed categories after browsing activity. The test output alone does not establish every candidate-set assertion described in the expanded steps.

**Status:** Pass (automated ranking; manual personalization verified)

**Related Jira Defect:** None

### TC-16-12: Use Renewal Time and Listing ID for Deterministic Ordering

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testEqualScoresUseRenewalAndListingIdTieBreakers`

**Objective:**
Verify deterministic tie-breaking when selecting equally scored candidates and when sorting the selected recommendations by Newest.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Controlled listings have known creation and renewal times.

**Test Data:**
Three eligible listings with equal category scores, varying effective recency, and optionally identical timestamps.

**Test Steps:**
1. Create listings with equal category scores and different creation/renewal timestamps.
2. Confirm the selection uses newest effective timestamp when category scores tie.
3. Request `recent` and verify the selected set is displayed by `COALESCE(renewed_at, created_at)` descending.
4. Give two listings the same effective timestamp and verify descending listing ID breaks the tie.

**Expected Result:**
- Equal-scored candidates use effective recency, then descending listing ID, for deterministic selection.
- Newest displays the selected candidates by effective recency, then descending listing ID.
- Selection and final display order are separate operations.

**Actual Result:**
Personalized Recommendations: `Equal scores use renewal and listing id tie breakers` passed in the complete PHPUnit suite, confirming the tested renewal-date and listing-ID ordering.

**Status:** Pass (automated)

**Related Jira Defect:** None

### TC-16-13: Use Full-Catalog Sorting for Guests and Users Without Category Interests

**Use Case:** UC-16

**Test Type:** Automated Integration / Manual Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testGuestAndNewUserReceiveNewestEligibleFallback`

**Objective:**
Verify that guests and signed-in users with no usable category-interest scores sort the entire eligible marketplace first and then receive up to ten results, instead of sorting a fixed set of ten newest listings.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- An unauthenticated session and a signed-in account without usable category-interest scores are available.
- Eligible listings span different dates and prices.

**Test Data:**
Guest user ID `null`; new buyer account; active listings with contrasting prices and dates; sort values `recent`, `price_low`, `price_high`.

**Test Steps:**
1. Open the homepage as a guest and record the listing IDs shown under Newest.
2. Select Price: Low to High and Price: High to Low, recording the results of each.
3. Verify the price sorts select the cheapest or most expensive eligible listings from the full marketplace, not only from the newest ten.
4. Repeat for a signed-in account without usable category scores.
5. Confirm both visitor types follow the same full-catalog sort-before-limit rule, except that the signed-in account excludes its own listings.

**Expected Result:**
- `recent` returns up to ten newest eligible listings.
- `price_low` returns up to ten cheapest eligible listings across the marketplace.
- `price_high` returns up to ten most expensive eligible listings across the marketplace.
- The returned listing set may differ across sort choices.
- Signed-in visitors still cannot see their own listings in their feed.

**Actual Result:**
Personalized Recommendations: `Guest and new user receive newest eligible fallback` passed in the complete PHPUnit suite. Separately, manual homepage testing confirmed that guests and signed-in users without usable activity can sort the full eligible marketplace by newest or price. The testdox name alone does not establish all expanded price-sort assertions.

**Status:** Pass (automated fallback; manual full-catalog sorting verified)

**Related Jira Defect:** None

### TC-16-14: Exclude a Viewer's Own Listings From Personalized Results

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testAuthenticatedFeedExcludesViewerOwnedListings`

**Objective:**
Verify that signed-in users do not receive recommendations for listings they own.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
An authenticated seller/viewer, another seller, one owned listing, and one other-seller listing.

**Test Steps:**
1. Create active listings owned by the current viewer and a different seller.
2. Retrieve recommendations for the authenticated viewer.
3. Retrieve general recommendations for a guest.
4. Compare presence of the owned listing in each response.

**Expected Result:**
- Signed-in results exclude the user's own listing.
- Another seller's listing remains eligible.
- The owned listing is not globally removed from the guest feed.

**Actual Result:**
The authenticated feed omitted the viewer-owned listing, while the other-seller listing and guest eligibility were retained. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-15: Exclude Unavailable Listings and Inactive Sellers From the Feed

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testFeedExcludesUnavailableListingsAndInactiveSellers`

**Objective:**
Verify that only active listings belonging to active sellers are recommended.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
One active control listing, listings in `sold`, `hidden`, `archived`, and `draft` states, and a restricted seller's listing.

**Test Steps:**
1. Create the eligible control listing and several ineligible listings.
2. Set the inactive listing statuses and restrict another seller account.
3. Retrieve the recommendation feed for the buyer.
4. Check inclusion and exclusion of each controlled listing ID.

**Expected Result:**
- The active control listing appears.
- Inactive listings and listings owned by restricted sellers are excluded.

**Actual Result:**
The active control was present and all ineligible listing IDs were absent. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-16: Sort the Same Ten Personalized Listings by Newest or Price

**Use Case:** UC-16

**Test Type:** Automated Integration / Manual Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testPriceSortOverridesRecommendationRelevance`

**Objective:**
Verify that once a signed-in user's recorded activity produces usable category scores, the helper selects the top ten recommendations by relevance and only reorders those same listing IDs for Newest and price sorting.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- The user has at least one eligible activity signal that contributes to a category score.
- The marketplace contains eligible listings with different dates and prices.

**Test Data:**
A buyer with recorded category interest, eligible recommendations at different prices, and additional eligible listings outside the top ten.

**Test Steps:**
1. Retrieve the feed with `recent` and record its listing IDs.
2. Retrieve `price_low` and confirm prices are nondecreasing.
3. Retrieve `price_high` and confirm prices are nonincreasing.
4. Compare the unordered ID sets across all three options.
5. Verify no lower-ranked marketplace listing replaces a selected personalized recommendation only because of its price.

**Expected Result:**
- The same up-to-ten recommendation IDs are returned under all three sort options while the activity scores and inventory remain unchanged.
- Newest orders that selected set by effective recency; the price sorts order only that set by price.
- Price sorting does not select the cheapest or most expensive items from the entire marketplace for a user with category interests.

**Actual Result:**
Personalized Recommendations: `Price sort overrides recommendation relevance` passed in the complete PHPUnit suite, confirming the tested price-sorting behavior. The final fixed-set rule for users with usable category interests was also checked manually; the testdox output alone does not show whether the test asserts equality of listing-ID sets across sort choices.

**Status:** Pass (automated price sorting; manual fixed-set verification)

**Related Jira Defect:** None

### TC-16-17: Handle Unsupported Sort Values and Enforce Result Limits

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testInvalidSortAndLimitValuesFallBackSafely`

**Objective:**
Verify that unsupported sort values and out-of-range requested limits are handled safely.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
Sort `invalid_sort`; limits `0` and `500`; newest active test listing.

**Test Steps:**
1. Request recommendations with an unsupported sort and limit zero.
2. Verify a single newest eligible listing is returned.
3. Request a result limit greater than the supported maximum.
4. Check that no more than 50 rows are returned.

**Expected Result:**
- Invalid sorts fall back to default ordering.
- The requested limit is clamped between 1 and 50.

**Actual Result:**
The invalid-sort request returned one eligible listing and the oversized-limit request returned at most 50. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None


### TC-16-18: Return Unique Listings With Homepage-Compatible Fields

**Use Case:** UC-16

**Test Type:** Automated Integration / Functional

**PHPUnit Source:** `tests/Functional/PersonalizedRecommendationsTest.php`  
**Automated Test:** `testResultsContainUniqueListingsAndHomepageCardFields`

**Objective:**
Verify that the recommendation result can safely populate the existing homepage listing-card component.

**Preconditions:**
- OmniBuy is running in Docker with the latest recommendation helper and MySQL schema.
- Each PHPUnit test uses temporary InnoDB fixtures with transaction rollback.

**Test Data:**
An active listing with a primary image and the existing homepage listing-card field requirements.

**Test Steps:**
1. Create an active listing and primary image record.
2. Retrieve recommended listings and collect their IDs.
3. Verify all listing IDs are unique.
4. Verify the primary image URL and required homepage listing-card fields are present.

**Expected Result:**
- No duplicate listing IDs appear in the result array.
- Each relevant result provides listing ID, condition, name, price, category, fulfillment, image URL, and category name.

**Actual Result:**
The test verified unique IDs, all required homepage-card fields, and the selected primary image URL. The targeted PHPUnit test and complete PHPUnit test suite were confirmed passing by the developer.

**Status:** Pass

**Related Jira Defect:** None

### TC-16-19: Activate Personalization After Eligible Search or Listing Activity

**Use Case:** UC-16, UC-17, UC-25

**Test Type:** Manual Functional / End-to-End

**Objective:**
Verify that a signed-in account transitions from full-marketplace fallback sorting to category-based candidate selection once recorded activity produces a usable category score.

**Preconditions:**
- OmniBuy is running in Docker.
- A signed-in test account initially has no usable category-interest scores.
- Eligible listings exist in multiple categories.

**Test Data:**
Test account without category interests; Electronics search terms and eligible Electronics listings.

**Test Steps:**
1. Record the initial homepage feed under the available sort choices.
2. Search for an Electronics item and open eligible Electronics listings owned by other sellers.
3. Return to the homepage and inspect the recommended listing categories.
4. Change the sort options and compare the selected personalized listing IDs.

**Expected Result:**
- Before usable activity, sorting searches the full eligible marketplace before applying the ten-result limit.
- After usable Electronics activity, Electronics is favored in the personalized candidate selection.
- Sorting the personalized feed does not change which selected recommendations are included, assuming unchanged activity and listing availability.

**Actual Result:**
Manual before-and-after testing confirmed that the homepage feed changed to feature more Electronics listings after searches and listing views.

**Status:** Pass (manual)

**Related Jira Defect:** None


### TC-16-20: Update Personalized Recommendations When Category Interests Change

**Use Case:** UC-16, UC-17, UC-25

**Test Type:** Manual Functional / End-to-End

**Objective:**
Verify that additional browsing in a different category changes recommendations according to the updated category-interest scores.

**Preconditions:**
- OmniBuy is running in Docker.
- A signed-in account has existing category-related search/view activity.

**Test Data:**
Existing Electronics activity; additional searches and eligible listing views in Home & Furniture.

**Test Steps:**
1. Record the personalized homepage listings after Electronics activity.
2. Search for Furniture-related items and view eligible listings in that category.
3. Reload the homepage and compare the recommended categories with the earlier feed.
4. Confirm the changes are consistent with the user's more recent and accumulated interests.

**Expected Result:**
- New eligible activity contributes to the appropriate category scores.
- Higher-scoring categories are prioritized in candidate selection.
- The recommendation set can change after meaningful new category activity.

**Actual Result:**
Manual testing confirmed that additional Furniture searches and listing views changed the feed from Electronics-focused to Furniture-focused recommendations.

**Status:** Pass (manual)

**Related Jira Defect:** None


### TC-16-21: Preserve Personalized Interests Across Logout and Login

**Use Case:** UC-16

**Test Type:** Manual Functional / End-to-End

**Objective:**
Verify that a signed-in user's personalization is derived from persisted database activity and remains available after logging out and back in.

**Preconditions:**
- OmniBuy is running in Docker.
- A test account has eligible saved search-history or listing-view activity.

**Test Data:**
Signed-in user with established category interests; login/logout session transitions.

**Test Steps:**
1. View and record the signed-in personalized homepage feed.
2. Log out and confirm the guest feed uses general marketplace sorting.
3. Log in again using the same account.
4. Return to the homepage and check that the account's category-based recommendations are restored.

**Expected Result:**
- Logout changes the feed to guest behavior without deleting recorded activity.
- Logging back into the same account restores personalization based on saved eligible activity.

**Actual Result:**
Manual testing previously confirmed that personalized recommendations persisted after logging out and logging back into the same account.

**Status:** Pass (manual)

**Related Jira Defect:** None


### TC-16-22: Exclude Own Listings From Every Signed-In Sorting Option

**Use Case:** UC-16

**Test Type:** Manual Functional / End-to-End / Negative Testing

**Objective:**
Verify that a signed-in user's own active listings are excluded from the recommendation feed regardless of personalized or fallback state and selected sort.

**Preconditions:**
- OmniBuy is running in Docker.
- A signed-in account can create an active listing.

**Test Data:**
A newly created listing owned by the signed-in test user; sort options Newest, Price: Low to High, and Price: High to Low.

**Test Steps:**
1. Create an active test listing using the signed-in account.
2. Open the homepage and check the feed under each sort option.
3. Confirm the owned listing ID is absent from all three feed results.
4. Confirm eligible listings belonging to other sellers remain available.

**Expected Result:**
- The user's own listing is not returned under any homepage sort option.
- Other sellers' eligible listings can still be returned.

**Actual Result:**
Manual testing confirmed that the newly created listing did not appear in the signed-in user's personalized feed under any of the three sorting options.

**Status:** Pass (manual)

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
Item Search: `Exact item search returns seeded listing` passed; the exact-match search returned the expected seeded listing.

**Status:** Pass (automated and manual)

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
Item Search: `Partial item search returns expected listing` passed; the partial keyword located the expected listing.

**Status:** Pass (automated and manual)

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
Item Search: `Item search displays no results state` passed; an unmatched item query displayed the expected empty state.

**Status:** Pass (automated and manual)

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
Item Search: `Item search excludes inactive listings` passed; inactive listings were excluded from item search results.

**Status:** Pass (automated and manual)

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


### TC-17-06: Record and Normalize an Authenticated Item Search

**Use Case:** UC-17

**Test Type:** Automated Integration

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testItemSearchIsSavedWithNormalizedQueryAndTimestamp`

**Objective:**
Verify that a valid item-search event is saved with its authenticated user, normalized query, search type, and timestamp.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary test user exists and the MySQL activity tables are available.

**Test Data:**
- User ID: temporary test user; query: `  Mechanical   Keyboard  `.

**Test Steps:**
1. Call `recordSearchActivity()` with the test user and padded keyword.
2. Query `search_history` for the temporary user.
3. Check the stored user ID, normalized query, search type, and timestamp.

**Expected Result:**
- Exactly one activity row is inserted.
- The query is saved as `Mechanical Keyboard`; `search_type` is `items`.
- The stored user ID is correct and `searched_at` is populated.

**Actual Result:**
Marketplace Activity: `Item search is saved with normalized query and timestamp` passed; a valid item search was recorded with its normalized keyword and timestamp.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-17-07: Prevent Duplicate Searches Within Five Minutes

**Use Case:** UC-17

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testSameSearchIsDeduplicatedWithinFiveMinutes`

**Objective:**
Verify that a repeated search by the same user is not counted twice within the five-minute window, including differences in capitalization and whitespace.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary test user and a writable `search_history` table are available.

**Test Data:**
- First query: `Wireless Keyboard`; repeated query: ` wireless   keyboard `.

**Test Steps:**
1. Record the first search using `recordSearchActivity()`.
2. Immediately submit the equivalent search with different case and spacing.
3. Count search-history rows for the temporary user.

**Expected Result:**
- The first call returns `true` and the repeated call returns `false`.
- Exactly one search-history row exists for the user.

**Actual Result:**
Marketplace Activity: `Same search is deduplicated within five minutes` passed; an equivalent repeated search did not create a duplicate event in the five-minute window.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-17-08: Allow a Search After the Five-Minute Deduplication Window

**Use Case:** UC-17

**Test Type:** Automated Integration

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testSameSearchCanBeRecordedAfterFiveMinutes`

**Objective:**
Verify that a new search is recorded after the previous matching search falls outside the five-minute window.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary test user exists and database timestamps can be adjusted within the test transaction.

**Test Data:**
- Keyword: `headphones`; previous event backdated six minutes.

**Test Steps:**
1. Record the initial search.
2. Backdate its `searched_at` timestamp by six minutes.
3. Call `recordSearchActivity()` again with the same query.
4. Count the recorded searches for the temporary user.

**Expected Result:**
- Both calls return `true`.
- Two activity rows exist because the earlier search is outside the deduplication window.

**Actual Result:**
Marketplace Activity: `Same search can be recorded after five minutes` passed; an item search could be recorded again after the deduplication interval.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-17-09: Keep Search Activity Separate by User and Keyword

**Use Case:** UC-17

**Test Type:** Automated Integration

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testSearchesAreIndependentByUserAndKeyword`

**Objective:**
Verify that different keywords and different users produce independent search-history records.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- Two temporary test users exist.

**Test Data:**
- User A: `laptop`, `camera`; User B: `laptop`.

**Test Steps:**
1. Record two distinct searches for User A.
2. Record the same first keyword for User B.
3. Query each user's search history independently.

**Expected Result:**
- User A has two records and User B has one record.
- Deduplication does not suppress a different keyword or another user's search.

**Actual Result:**
Marketplace Activity: `Searches are independent by user and keyword` passed; independent users and keywords were tracked separately.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-17-10: Reject Invalid or Oversized Search Activity

**Use Case:** UC-17

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testInvalidSearchActivityIsNotSaved`

**Objective:**
Verify that invalid user IDs, blank queries, and queries exceeding the schema length are rejected without inserts.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary test user exists.

**Test Data:**
- User ID: `0`; blank/whitespace-only queries; keyword of 256 bytes.

**Test Steps:**
1. Call `recordSearchActivity()` with a nonpositive user ID.
2. Repeat with empty and whitespace-only queries.
3. Repeat with a 256-byte query for the test user.
4. Check for any inserted history records.

**Expected Result:**
- Every invalid call returns `false`.
- No search-history rows are inserted for the test user.

**Actual Result:**
Marketplace Activity: `Invalid search activity is not saved` passed; invalid search input was rejected without a saved activity event.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-17-11: Handle Search Activity Database Write Failures Safely

**Use Case:** UC-17

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testSearchTrackingWriteErrorIsHandledWithoutThrowing`

**Objective:**
Verify that activity recording fails safely without interrupting a calling page when PDO throws an insertion error.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A controlled PDO test double throws `PDOException` on `prepare()`.

**Test Data:**
- Valid item search and simulated database failure.

**Test Steps:**
1. Call `recordSearchActivity()` using the failing PDO test double.
2. Inspect the function return value and whether an exception escapes.

**Expected Result:**
- The function catches the PDO error, logs it, and returns `false`.
- No uncaught database exception is propagated to the caller.

**Actual Result:**
Marketplace Activity: `Search tracking write error is handled without throwing` passed; the simulated database write failure was handled without propagating an exception.

**Status:** Pass (automated)

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
Seller Search: `Exact seller search returns seeded seller` passed; exact-match seller search returned the expected active seller.

**Status:** Pass (automated and manual)

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
Seller Search: `Partial seller search returns expected seller` passed; a partial seller query returned the expected profile.

**Status:** Pass (automated and manual)

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
Seller Search: `Partial seller search returns expected seller` passed, providing partial-name search coverage. Manual test output establishes that each separate username, first-name, visible-last-name, and full-name variation in this broader case was exercised.

**Status:** Pass (automated partial-name search; manual name variations search)

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
Seller Search: `Seller search displays no results state` passed; an unmatched seller query produced the expected empty result.

**Status:** Pass (automated and manual)

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
Seller Search: `Seller search excludes inactive sellers` passed; inactive sellers were excluded from search results.

**Status:** Pass (automated and manual)

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
Seller Search: `Seller search does not expose restricted data` passed; the test checked that restricted seller data was not rendered in search results.

**Status:** Pass (automated)

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

**Test Type:** Manual Functional / Negative Testing (automated direct-parameter coverage not established)

**Objective:**
Verify that invalid or nonexistent category identifiers
are handled safely.

**Preconditions:**
- OmniBuy is running in Docker.

**Test Data:**
- Nonexistent numeric category ID supplied directly in the search-page URL.
- Non-numeric or array-valued category parameters remain additional cases for separate verification.

**Test Steps:**
1. Open the marketplace search page using a URL with a nonexistent category ID.
2. Examine the result list and any error state.
3. Confirm that no unexpected listings are displayed and the page does not crash.
4. Optionally repeat with malformed/non-numeric or array-valued parameters for additional negative-test coverage.

**Expected Result:**
- A nonexistent category ID should not produce a PHP or SQL error.
- The search page should safely show no matching results rather than silently selecting a different category.
- Invalid category IDs must not expose unrelated marketplace listings or internal diagnostics.

**Actual Result:**
Manual testing confirmed that replacing the category ID in the search-page URL with a nonexistent category ID returned no results. The page handled the request safely. This is a manual observation; the non-numeric and array-valued parameter cases have not been separately confirmed, and `CategoryFilterTest.php` does not directly test arbitrary URL parameter values.

**Status:** Pass (manual for nonexistent category ID; other malformed types unverified)

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

### TC-19-14: Handle a Nonexistent Category Page

**Use Case:** UC-19

**Test Type:** Manual Functional / Negative Testing

**Objective:**
Verify that navigating directly to a category page with a nonexistent category identifier produces a clear not-found state and a working route back to the marketplace homepage.

**Preconditions:**
- OmniBuy is running in Docker.
- Category pages are accessible through the marketplace navigation.

**Test Data:**
- A nonexistent category identifier entered directly in a category-page URL.

**Test Steps:**
1. Open a category-page URL and replace its category identifier with a nonexistent value.
2. Load the modified category-page URL.
3. Observe the error message and available navigation.
4. Use the return-to-homepage button.

**Expected Result:**
- The page displays `Category Not Found` instead of presenting a nonexistent category as valid.
- A button is provided to return to the homepage.
- Following the button navigates to the homepage.
- The request is handled without a visible application crash.

**Actual Result:**
Manual testing confirmed that a category-page URL containing a nonexistent category identifier displayed `Category Not Found` and provided a button redirecting the user to the homepage. No dedicated automated category-page not-found test has been identified.

**Status:** Pass (manual)

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
Manual testing of the combined item-keyword, category, and price-range filters was completed successfully on the OmniBuy website. The combined filtering behavior was confirmed to work as expected. This result is based on user-reported manual testing; `PriceRangeFilterTest.php` does not contain a dedicated automated assertion for all three filters in one request.

**Status:** Pass (manual; dedicated combined-filter automated test not confirmed)

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
Search Page: `Meetup filter matches database` passed in the complete PHPUnit suite. The existing manual result also confirms meetup-only listings, selected control state, and filter visibility.

**Status:** Pass (automated and manual)

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
Automated testing using PHPUnit confirmed that meetup listings located more than 10 miles from ZIP code 92831 were excluded from the search results. The test retrieved active meetup listings outside the selected radius from MySQL and verified that they appeared when no radius was applied. After applying the 10-mile radius filter, all identified distant listings were excluded, while a temporary nearby meetup listing remained visible. The page loaded successfully without PHP or database errors, and the temporary listing was removed after testing.

Manual testing was performed through the local OmniBuy website by selecting Meetup, entering ZIP code 92831, and applying a 10-mile radius. Distant meetup listings that were visible without the radius filter were no longer displayed after applying it. The page displayed the expected no-results message with the existing seed data, and no PHP or database errors occurred.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that the meetup radius filter correctly handled listings at and around the 10-mile boundary of ZIP code 92831. Three temporary meetup listings were created at approximately 9.99, 10.00, and 10.01 miles from the selected location. The distances were calculated independently using PHP before being tested against the search page. The listing within the radius and the listing at the closest representable boundary coordinate were included in the results, while the listing beyond 10 miles was excluded. The page loaded successfully without PHP or database errors, and all temporary test listings were removed after testing.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that selecting a 10-mile meetup radius without entering a buyer ZIP code produced the expected validation error. The test first verified that active meetup listings were available without a radius filter. When the radius was applied with a blank ZIP code, the application displayed the "Invalid Meetup Location" message requesting a valid five-digit ZIP code. No listing cards were returned, and the result count remained zero, confirming that the application did not display unfiltered results. The ZIP field remained blank, the 10-mile radius selection was preserved, and no PHP or database errors occurred.

Manual testing was performed through the local OmniBuy website by selecting Meetup, leaving the ZIP-code field blank, and applying a 10-mile radius. The search results displayed the expected validation message instead of returning unfiltered listings. No listings were displayed, and the page remained functional without PHP or database errors.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that the meetup radius filter correctly rejected both invalid ZIP-code formats and ZIP codes missing from the reference database. The test submitted 12A45 to verify format validation and confirmed that 00000 was absent from the zip_code_locations table before testing the unknown ZIP scenario. Both inputs produced their corresponding validation messages, and no unfiltered listing results were returned. The result count remained zero, the entered ZIP codes and selected 10-mile radius were preserved, and the page loaded without PHP or database errors.

Manual testing was performed through the local OmniBuy website by selecting Meetup and applying a 10-mile radius with invalid location information. Entering 12A45 was rejected by the browser's ZIP-code validation, and a direct request confirmed that the PHP backend also displayed the appropriate five-digit ZIP-code error. Entering the unknown ZIP code 00000 displayed the expected ZIP-code-not-found message. Neither scenario returned unfiltered listings, and the page remained functional without PHP or database errors.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that all four supported meetup radius selections (5, 10, 25, and 50 miles) returned the expected listings for ZIP code 92831. Five temporary meetup listings were created at approximately 3, 8, 20, 40, and 55 miles to verify the behavior of each radius option. The returned listing IDs matched the corresponding MySQL distance queries, confirming that eligible listings were included and listings beyond each selected radius were excluded. The test also verified that increasing the radius did not remove listings previously included at smaller distances. All requests completed without PHP or database errors, and the temporary listings were removed after testing.

Manual testing was performed through the local OmniBuy website by selecting Meetup, entering ZIP code 92831, and testing each supported radius individually. The displayed listings were compared against their known distances, and increasing the radius preserved previously eligible results while including additional listings when available. Listings outside the selected distance were excluded, and the page remained functional without PHP or database errors.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that applying a 10-mile radius to ZIP code 92831 without selecting a delivery method preserved all active shipping listings while restricting meetup listings based on distance. Three temporary listings were created at approximately 3, 20, and 40 miles to verify the behavior of nearby meetup, distant meetup, and distant shipping listings. The nearby meetup and distant shipping listings appeared in the results, while the meetup listing outside the radius was excluded. The returned listing IDs matched the expected MySQL query results, confirming that the radius did not restrict shipping listings. The ZIP code and radius selections remained applied, neither delivery method was selected, and the page loaded without PHP or database errors. All temporary listings were removed after testing.

Manual testing was performed through the local OmniBuy website by leaving both delivery methods unselected, entering ZIP code 92831, and applying a 10-mile radius. Active shipping listings remained visible, while meetup listings beyond the selected distance were excluded. The selected ZIP code and radius were preserved, and the page displayed no PHP or database errors.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that meetup listings with missing latitude or longitude coordinates were correctly excluded from radius-filtered search results. Three temporary meetup listings were created for testing: one with a NULL latitude, one with a NULL longitude, and one with valid coordinates corresponding to ZIP code 92831. The test first verified that all three listings appeared when no radius filter was applied. After applying a 10-mile meetup radius, both listings with missing coordinates were excluded, while the valid nearby listing remained visible. The page loaded successfully without PHP or database errors, and all temporary test listings were removed after testing.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that OmniBuy's meetup distance calculations produced accurate and consistent results for known geographic coordinates. Four temporary meetup listings were created at predetermined locations relative to ZIP code 92831, with expected distances of approximately 0, 3.4547, 17.9510, and 22.9428 miles. The test used MySQL's ST_Distance_Sphere function to calculate distances in meters, converted the results to miles, and compared them against independently calculated reference distances. All calculated values fell within the defined 0.02-mile tolerance. Repeating the calculations with identical coordinates produced consistent results, confirming that the distance calculations were repeatable. No PHP or database errors occurred, and all temporary test listings were removed after testing.

**Status:** Pass

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
Automated testing using PHPUnit confirmed that meetup radius filtering worked correctly alongside condition filtering, delivery method filtering, and distance sorting. Seven temporary listings were created with different distances, conditions, delivery methods, and statuses to verify the combined behavior. Applying the Meetup and Good condition filters with a 25-mile radius from ZIP code 92831 returned only eligible active listings. Listings outside the radius, listings with other conditions, shipping listings, and inactive listings were excluded. After selecting Distance: Near to Far, the returned listing IDs matched the expected MySQL results in ascending distance order. All selected filters remained preserved, no PHP or database errors occurred, and the temporary test listings were removed after testing.

Manual testing was performed through the local OmniBuy website by selecting Meetup, Good condition, ZIP code 92831, and a 25-mile radius before changing the sort order to Distance: Near to Far. The search results reflected the selected filters, and the selections remained applied after sorting. Available results were checked for eligibility and correct ordering where multiple matching listings were present. The page remained functional without PHP or database errors.

**Status:** Pass

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
Listing Detail: `Active listing displays its database information` passed; an active listing detail page returned the expected listing information.

**Status:** Pass (automated)

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
Listing Detail: `Active listing displays its database information`, `Shipping information matches database`, `Meetup information matches database without exposing coordinates`, and listing-image tests all passed; the rendered listing detail data matched tested database values.

**Status:** Pass (automated and manual)

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
Listing Detail: `Missing listing id is rejected`, `Invalid listing id is rejected` (all displayed datasets), and `Nonexistent listing is unavailable` passed; invalid and nonexistent identifiers were handled safely.

**Status:** Pass (automated and manual)

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
Listing Detail: `Inactive listing cannot be opened` passed for hidden, sold, archived, and draft datasets. `Listing from inactive seller is unavailable` also passed.

**Status:** Pass (automated and manual)

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
Listing Detail: `Listing id selects the correct listing` passed; requests returned the selected listing instead of another listing's data.

**Status:** Pass (automated)

**Related Jira Defect:** None

### TC-25-06: Record a Valid Authenticated Listing View

**Use Case:** UC-25

**Test Type:** Automated Integration

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testEligibleListingViewIsSavedWithTimestamp`

**Objective:**
Verify that viewing an active listing owned by another active user creates a listing-view activity record.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary active seller, buyer, category, and active listing exist.

**Test Data:**
- Authenticated buyer ID; active listing ID belonging to another seller.

**Test Steps:**
1. Call `recordListingView()` with the buyer and active listing IDs.
2. Retrieve the buyer's `listing_views` records.
3. Compare the stored IDs and timestamp to the fixture.

**Expected Result:**
- The helper returns `true` and inserts exactly one record.
- User ID and listing ID match; `viewed_at` is populated.

**Actual Result:**
Marketplace Activity: `Eligible listing view is saved with timestamp` passed; the buyer’s eligible listing-view event was saved.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-07: Prevent Duplicate Listing Views Within Fifteen Minutes

**Use Case:** UC-25

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testSameListingViewIsDeduplicatedWithinFifteenMinutes`

**Objective:**
Verify that refreshing the same listing does not create another activity event within fifteen minutes.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- An active listing owned by a temporary seller and a separate buyer exist.

**Test Data:**
- Same buyer and listing submitted twice in succession.

**Test Steps:**
1. Record a valid listing view.
2. Immediately call `recordListingView()` again for the same pair.
3. Count the buyer's recorded views.

**Expected Result:**
- The first call returns `true`; the second returns `false`.
- Exactly one view exists for the buyer/listing pair.

**Actual Result:**
Marketplace Activity: `Same listing view is deduplicated within fifteen minutes` passed; refreshing a listing within the interval did not create a duplicate view.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-08: Allow a Listing View After the Fifteen-Minute Window

**Use Case:** UC-25

**Test Type:** Automated Integration

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testListingViewCanBeRecordedAfterFifteenMinutes`

**Objective:**
Verify that a view may be recorded again once an earlier matching view is outside the fifteen-minute deduplication window.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary active seller, buyer, and listing exist.

**Test Data:**
- Previous view backdated sixteen minutes.

**Test Steps:**
1. Record the initial buyer view.
2. Backdate its `viewed_at` timestamp by sixteen minutes.
3. Record another view of the same listing.
4. Count the buyer's view records.

**Expected Result:**
- Both view calls return `true`.
- Two view rows exist because the first event is outside the window.

**Actual Result:**
Marketplace Activity: `Listing view can be recorded after fifteen minutes` passed; a subsequent view outside the deduplication window was saved.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-09: Exclude Sellers Viewing Their Own Listings

**Use Case:** UC-25

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testSellerOwnListingViewIsNotSaved`

**Objective:**
Verify that a seller opening their own listing does not generate a buyer-interest signal.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary active seller owns an active listing.

**Test Data:**
- Viewer ID equals the listing's `seller_user_id`.

**Test Steps:**
1. Call `recordListingView()` using the seller's own user ID and listing ID.
2. Inspect the seller's `listing_views` records.

**Expected Result:**
- The helper returns `false`.
- No view row is created for the seller viewing their own listing.

**Actual Result:**
Marketplace Activity: `Seller own listing view is not saved` passed; viewing an owned listing did not create the buyer-interest event.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-10: Exclude Inactive Listings From View Tracking

**Use Case:** UC-25

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testInactiveListingsDoNotGenerateViews`

**Objective:**
Verify that sold, hidden, archived, and draft listings cannot create new listing-view activity.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- An active seller, separate buyer, and temporary listing exist.

**Test Data:**
- Listing statuses: `sold`, `hidden`, `archived`, `draft`.

**Test Steps:**
1. Change the test listing to each inactive status in turn.
2. Attempt to record the buyer's view for each status.
3. Inspect the buyer's view history.

**Expected Result:**
- Every attempt returns `false`.
- No views are recorded while the listing is inactive.

**Actual Result:**
Marketplace Activity: `Inactive listings do not generate views` passed; inactive listings did not produce new view records.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-11: Exclude Listings From Inactive Seller Accounts

**Use Case:** UC-25

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testInactiveSellerListingsDoNotGenerateViews`

**Objective:**
Verify that an active listing cannot generate views while its seller is suspended or restricted.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- An active listing and separate buyer exist; the seller's account status can be modified in the test transaction.

**Test Data:**
- Seller states: `suspended`, `restricted`, then `active`.

**Test Steps:**
1. Set the seller account to suspended and attempt a view.
2. Repeat with the restricted account state.
3. Confirm no views were recorded.
4. Restore the seller to active and repeat the view.

**Expected Result:**
- Suspended/restricted states yield `false` and zero records.
- Restoring active status allows one eligible view.

**Actual Result:**
Marketplace Activity: `Inactive seller listings do not generate views` passed; listings owned by inactive sellers did not produce eligible view records.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-12: Keep Listing Views Separate by Buyer and Listing

**Use Case:** UC-25

**Test Type:** Automated Integration

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testListingViewsAreIndependentByBuyerAndListing`

**Objective:**
Verify that view deduplication applies to a specific user/listing pair, not to an entire buyer or listing.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- One active seller has two active listings; two separate buyers exist.

**Test Data:**
- Buyer A views Listings A and B; Buyer B views Listing A.

**Test Steps:**
1. Record the three distinct buyer/listing interactions.
2. Inspect each buyer's stored views.
3. Verify that the correct listing IDs belong to the correct buyers.

**Expected Result:**
- All three calls return `true`.
- Buyer A has two rows; Buyer B has one row for Listing A.

**Actual Result:**
Marketplace Activity: `Listing views are independent by buyer and listing` passed; view events remained independent for distinct buyer/listing pairs.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-13: Reject Invalid or Unknown Listing View Identifiers

**Use Case:** UC-25

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testInvalidListingViewIdentifiersAreRejected`

**Objective:**
Verify that invalid user IDs, invalid listing IDs, and nonexistent listings cannot generate view rows.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A temporary seller, buyer, and active listing exist.

**Test Data:**
- Invalid user ID `0`; listing ID `0`; nonexistent listing ID.

**Test Steps:**
1. Attempt to record a view with user ID 0.
2. Attempt to record a view with listing ID 0.
3. Attempt to record a view with a nonexistent listing ID.
4. Inspect the buyer's view history.

**Expected Result:**
- Every attempt returns `false`.
- No invalid view records are created.

**Actual Result:**
Marketplace Activity: `Invalid listing view identifiers are rejected` passed; invalid or nonexistent identifiers were rejected.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-14: Handle Listing View Database Write Failures Safely

**Use Case:** UC-25

**Test Type:** Automated Integration / Negative Testing

**PHPUnit Source:** `tests/Functional/MarketplaceActivityTest.php`
**Automated Test:** `testListingViewWriteErrorIsHandledWithoutThrowing`

**Objective:**
Verify that a database insertion failure does not propagate an uncaught exception from view tracking.

**Preconditions:**
- OmniBuy test database is accessible via Docker/PDO.
- A controlled PDO test double throws `PDOException` on `prepare()`.

**Test Data:**
- Valid buyer/listing identifiers and simulated database failure.

**Test Steps:**
1. Call `recordListingView()` using the failing PDO test double.
2. Inspect the helper's return value and exception behavior.

**Expected Result:**
- The helper catches and logs the PDO error and returns `false`.
- No uncaught database exception reaches the caller.

**Actual Result:**
Marketplace Activity: `Listing view write error is handled without throwing` passed; a simulated database write failure was handled safely.

**Status:** Pass (automated)

**Related Jira Defect:** None


### TC-25-15: Display Parent Category and Subcategory Separately

**Use Case:** UC-25

**Test Type:** Automated Functional / Database Integration

**Objective:**
Verify that a listing assigned to a subcategory displays its parent category under Category and its assigned child category under Subcategory in the About this item specifications.

**Preconditions:**
- OmniBuy is running in Docker with an isolated test database.
- Categories include at least one parent-child relationship.
- An active seller account exists.

**Test Data:**
- A parent category and its child category selected from MySQL (for example, Electronics and Computers).
- A temporary active listing assigned to the child category.

**Test Steps:**
1. Retrieve a parent-child category pair from the database.
2. Create a temporary active listing assigned to the child category.
3. Request the listing detail page over HTTP.
4. Locate the Category and Subcategory rows within About this item.
5. Compare both displayed values with the parent and child names stored in MySQL.
6. Clean up the temporary listing.

**Expected Result:**
- Exactly one Category row displays the parent category name.
- Exactly one Subcategory row displays the assigned child category name.
- The two values are not incorrectly swapped or combined.
- The listing page loads without PHP or database errors.

**Automated PHPUnit Test:** `testSubcategoryListingDisplaysParentCategoryAndSubcategory` in `tests/Functional/ListingDetailTest.php`

**Actual Result:**
Listing Detail: `Subcategory listing displays parent category and subcategory` passed; the listing detail page displayed the correct parent and child category fields.

**Status:** Pass (automated and manual)

**Related Jira Defect:** None


### TC-25-16: Hide Subcategory Field for a Parent-Only Listing

**Use Case:** UC-25

**Test Type:** Automated Functional / Database Integration / Boundary Testing

**Objective:**
Verify that a listing assigned directly to a top-level category displays its category name without an empty or misleading Subcategory row.

**Preconditions:**
- OmniBuy is running in Docker with an isolated test database.
- A top-level category exists (`parent_category_id IS NULL`).
- An active seller account exists.

**Test Data:**
- A root category retrieved from MySQL (for example, Books).
- A temporary active listing assigned directly to that root category.

**Test Steps:**
1. Retrieve a top-level category from the database.
2. Create a temporary active listing assigned directly to that category.
3. Request its listing detail page over HTTP.
4. Inspect the Category and Subcategory specification rows.
5. Compare the Category value with the database record.
6. Clean up the temporary listing.

**Expected Result:**
- Exactly one Category row displays the root category name.
- No Subcategory row is rendered.
- No blank or incorrect subcategory value appears.
- The listing page loads without PHP or database errors.

**Automated PHPUnit Test:** `testParentCategoryListingDoesNotDisplaySubcategoryField` in `tests/Functional/ListingDetailTest.php`

**Actual Result:**
Listing Detail: `Parent category listing does not display subcategory field` passed; a root-category listing omitted the Subcategory row.

**Status:** Pass (automated and manual)

**Related Jira Defect:** None
