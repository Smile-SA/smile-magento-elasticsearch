/**
 * ElaticSearch tracking indexer plugin. Customer data indexation.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
package eu.smile.es.tracking.indexer.magento;
import static org.elasticsearch.common.xcontent.XContentFactory.*;
import org.elasticsearch.action.update.UpdateRequest;
import org.elasticsearch.common.xcontent.XContentBuilder;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;
import eu.smile.es.tracking.collect.Processor;

/**
 * Customer data indexation.
 * 
 * @author Aurelien FOUCRET <aufou@smile.fr>
 */
public class CustomerSession extends Processor {
	
	/**
	 * List of url vars ignored by the processor
	 */
	private String[] excludedPrefix = new String[]{"page.rum", "page.u", "page.r", "page.r2", "page.order"};
	
	/**
	 * Bulk update script used to update a customer session. 
	 */
	private String script = "ctx._source.pages = ctx._source.containsKey('pages') ? ctx._source.pages : [];" +
							"if (ctx._source.pages.size() < 100) {ctx._source.pages += page;} else {ctx._source.is_spam = true;}";
	
	/**
	 * Process the event.
	 * 
	 * @param data Data to be processed
	 */
	public void processData(Map <String, String> data)
	{
		try {;
			UpdateRequest request = new UpdateRequest(this.getIndexName(), "session", data.get("session.uid"));
			Map<String, Object> pageData = getPageParams(data);
			request.script(this.script, pageData);
			request.upsert(getSessionData(data, pageData));
			indexer.addRequestToBulk(request);
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
	protected Map <String, Object> getPageParams(Map <String, String> data)
	{
		Map <String, Object> pageData = new HashMap <String, Object>();
		
		for (Map.Entry<String, String> variable : data.entrySet()) {
			
			String varName = variable.getKey();
			boolean addVariable = true;
			
			for (String prefix: excludedPrefix) {
				if (varName.startsWith(prefix)) {
					addVariable = false;
				}
			}
			
			if (addVariable) {
				Map <String, Object> currentContainer = pageData;
				while (varName.length() > 0 && varName.indexOf(".") != -1) {
					String prefix = varName.substring(0, varName.indexOf("."));
					varName = varName.substring(varName.indexOf(".") + 1, varName.length());
					if (!currentContainer.containsKey(prefix)) {
						currentContainer.put(prefix, new HashMap<String, Object>());	
					}
					currentContainer = (Map<String, Object>) currentContainer.get(prefix); 
				}
				currentContainer.put(varName, variable.getValue());
			}
		}
		return pageData;
	}
	
	/**
	 * Exctract session data from the raw event.
	 * 
	 * @param data Data to be processed
	 * 
	 * @return Indexed params
	 */
	protected XContentBuilder getSessionData(Map <String, String> data, Map<String, Object> pageData) throws IOException
	{
		XContentBuilder sessionData = null;
		ArrayList<Map <String, Object>> pages = new ArrayList<Map <String, Object>>();
		pages.add((Map<String, Object>) pageData.get("page"));
		
		sessionData = jsonBuilder()
			.startObject()
				.field("session_id", data.get("session.uid"))
				.field("session_date", new Date())
				.field("pages", pages)
			.endObject();
		
		return sessionData;
	}
}
