/**
 * ElaticSearch tracking indexer plugin. Product popuylarity indexation.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
package eu.smile.es.tracking.indexer.magento;
import static org.elasticsearch.common.xcontent.XContentFactory.jsonBuilder;
import java.io.IOException;
import java.text.DateFormat;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Map;
import org.elasticsearch.action.update.UpdateRequest;
import org.elasticsearch.common.xcontent.XContentBuilder;
import eu.smile.es.tracking.collect.Processor;


public class PopularProduct extends Processor {

	/**
	 * Bulk update script used to update a customer session. 
	 */
	static DateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd");
	
	/**
	 * Bulk update script used to update a customer session. 
	 */
	private String script = "ctx._source.count += 1";
	
	/**
	 * Process the event.
	 * 
	 * @param data Data to be processed
	 */
	public void processData(Map <String, String> data)
	{
		try {
			if (data.containsKey("page.product.id") && data.containsKey("page.store_id")) {
				this.processProductPageData(data);
			} else if (data.containsKey("page.order.id") && data.containsKey("page.store_id")) {
				this.processOrderData(data);
			}
		} catch (IOException e) {
			logger.error("IO Exception while saving session " + data.get("session.uid"));
		} catch (Exception e) {
			logger.error("Exception while saving session " + e.toString());
		}
	}
	
	/**
	 * Extract page data from the raw event.
	 * 
	 * @param data Data to be processed
	 * 
	 * @return Indexed params
	 */
	protected void processProductPageData(Map <String, String> data) throws IOException 
	{
		String productId   = data.get("page.product.id");
		String storeId     = data.get("page.store_id");
		addRequest(productId, storeId, "product_view");
	}
	
	/**
	 * Extract order data from the raw event.
	 * 
	 * @param data Data to be processed
	 * 
	 * @return Indexed params
	 */
	protected void processOrderData(Map <String, String> data) throws IOException {
		String storeId = data.get("page.store_id");
		for (Map.Entry<String, String> entry : data.entrySet()) {
			if (entry.getKey().matches("page[.]order[.]items[.].*[.]product_id")) {
				String productId = entry.getValue();
				addRequest(productId, storeId, "product_order");
			}
		}
	}
	
	/**
	 * Add request to indexing
	 * 
	 * @param productId Affected product id
	 * @param storeId   Affected product id
	 * @param eventType Event type (product_order, product_view)
	 * 
	 * @return Indexed params
	 */
	protected void addRequest(String productId, String storeId, String eventType) throws IOException {
		
		String currentDate = dateFormat.format(new Date());
		String uniqueId    = productId + "|" + storeId + "|" + eventType + "|" + currentDate;
		UpdateRequest updateRequest = new UpdateRequest(this.getIndexName(), "stats", uniqueId);
		updateRequest.script(script);
		
		updateRequest.parent(productId + "|" + storeId);
		XContentBuilder upsert = jsonBuilder()
			.startObject()
				.field("_parent", productId + "|" + storeId)
				.field("product_id", productId)
				.field("store_id", storeId)
				.field("event_type", eventType)
				.field("count", 1)
				.field("date", currentDate)
			.endObject();
		
		updateRequest.upsert(upsert);
		
		indexer.addRequestToBulk(updateRequest);
	}
}
