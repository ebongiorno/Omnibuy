# Sprint 3 – Manual Listing Creation Test Plan

**Project:** OmniBuy  
**Tester:** Bryant Huynh  
**Sprint:** Sprint 3  
**Feature:** Listing Creation and Seller Management  
**Status:** Planned – Testing Pending

## Objective

Verify that sellers can create listings using valid product information and that invalid inputs are handled correctly.

These manual tests will help identify issues with listing creation, form validation, database publication, and seller ownership.

## Manual Test Cases

### TC-01: Valid Listing Information
- Enter a valid title, price, and description.
- Click Continue.
- Expected: User proceeds to Listing Details.
- Status: Not Run

### TC-02: Missing Listing Title
- Leave the title field empty.
- Enter valid information in the remaining fields.
- Click Continue.
- Expected: Submission is blocked and a validation message appears.
- Status: Not Run

### TC-03: Invalid Listing Price
- Enter a nonnumeric or negative price.
- Click Continue.
- Expected: Invalid price is rejected.
- Status: Not Run

### TC-04: Missing Description
- Leave the description empty.
- Click Continue.
- Expected: Submission is blocked.
- Status: Not Run

### TC-05: Category Selection
- Navigate to Listing Details.
- Select a category from the database.
- Expected: Available categories display correctly.
- Status: Not Run

### TC-06: Listing Condition
- Select a valid condition such as Like New.
- Expected: Condition is accepted.
- Status: Not Run

### TC-07: Quantity Validation
- Enter zero or a negative quantity.
- Attempt to publish.
- Expected: Invalid quantity is rejected.
- Status: Not Run

### TC-08: Delivery Method
- Select Shipping or Meetup.
- Expected: The selected delivery method is accepted.
- Status: Not Run

### TC-09: Listing Publication
- Complete the listing form with valid information.
- Publish the listing while authenticated as a seller.
- Expected: Listing is saved successfully to the database.
- Status: Not Run

### TC-10: My Listings Display
- Publish a listing.
- Open My Listings.
- Expected: The new listing appears under the correct seller account.
- Status: Not Run

## Testing Evidence

For each executed test, record:
- Actual result
- Pass/Fail/Blocked status
- Screenshot or error message
- Related Jira issue, if applicable

## Known Limitations

- Authentication integration still requires verification.
- Complete listing publication has not been verified.
- Browser testing is pending.
- Any blocked or failed tests will be documented during execution.

## Conclusion

This checklist defines the manual testing activities planned for Sprint 3. Test execution and results will be added after the Listing Creation workflow is available for testing.
