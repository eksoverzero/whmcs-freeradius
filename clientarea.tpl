<p style="text-align:left">
  <h3>Usage Stats for this Billing Period</h3>
  <strong># of Logins:</strong> {$logins}<br>
  <strong>Accumalated Hours Online:</strong> {$logintime}<br>
  <strong>Total Usage:</strong> {$total}<br>
  <strong>Uploads:</strong> {$uploads}<br>
  <strong>Downloads:</strong> {$downloads}<br>
  <strong>Usage Limit:</strong> {$limit}<br>
  <strong>Status:</strong> {$status}
</p>

{php}
/*

As per the example output above:

logins: Number of sessions
logintime: Time spent online in this billing period. Convertered into a readable format
total: Total usage for this billing period. Convertered into a readable format
uploads: Upload usage for this billing period. Convertered into a readable format
downloads: Download usage for this billing period. Convertered into a readable format
limit: Usage Limit for this billing period. Convertered into a readable format
status: Status on the radius server. Login time or Offline


The following are also avaible in unconverted format. For use when creating a chart or using your own converstions

logintime_seconds: Time spent online in this billing period. In seconds
total_bytes: Total usage for this billing period. In Bytes
uploads_bytes: Upload usage for this billing period. In Bytes
downloads_bytes: Download usage for this billing period. In Bytes
limit_bytes: Usage Limit for this billing period. In Bytes

*/
{/php}