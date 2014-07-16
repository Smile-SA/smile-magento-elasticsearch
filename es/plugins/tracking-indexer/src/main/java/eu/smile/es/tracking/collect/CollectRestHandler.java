/**
 * ElaticSearch tracking indexer plugin. HTTP endpoint.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
package eu.smile.es.tracking.collect;
import java.util.LinkedList;
import java.util.Map;

import org.elasticsearch.rest.*;
import org.elasticsearch.common.inject.Inject;
import org.elasticsearch.client.Client;
import org.elasticsearch.common.settings.Settings;

import static org.elasticsearch.rest.RestRequest.Method.GET;
import static org.elasticsearch.rest.RestStatus.OK;

/**
 * Rest Handler implementation
 * 
 * @author Aurelien FOUCRET <aufou@smile.fr>
 */
public class CollectRestHandler extends BaseRestHandler {
	
	/**
	 * Indexer used to process events collected through the HTTP REST handler.
	 */
	private Indexer indexer;
	
	
	/**
	 * Constructor :
	 * - Append a new route for events collection into the REST API
	 * - Create an indexer and start the indexation of collected events
	 * 
	 * @param settings   Search engine settings.
	 * @param client     Search engine access through a client.
	 * @param controller Controller where to append the REST route.
	 */
    @Inject
    public CollectRestHandler(Settings settings, Client client, RestController controller) {
    	super(settings, client);
        controller.registerHandler(GET, "/tracker/hit", this);
        indexer = new Indexer(client);
        indexer.start();
    }
    
    /**
     * Query execution.
     * 
     * @param request Request to be proccessed.
     * @param channel Response channel.
     */
    public void handleRequest(final RestRequest request, final RestChannel channel) {
    	CollectRestHandler.Buffer.addQuery(request.params());
        channel.sendResponse(new BytesRestResponse(OK, ""));
    }
     
    /**
     * Static class implementing a buffer used to store events pending indexation.
     */
    public static class Buffer {
    	
    	/**
    	 * Buffered to store the list.
    	 */
    	static LinkedList<Map <String, String>> buffer = new LinkedList<Map <String, String>>();
		
    	/**
    	 * Append a query to be processed into the buffer
    	 * 
    	 * @param query Query content
    	 */
		static public void addQuery(Map <String, String> query) {
			synchronized(buffer) {
				buffer.add(query);
			}
		}
		
		/**
		 * This method is used to retrieve the list of all events to be processed.
		 * It empty the list and return it.
		 * 
		 * @return The list of all query to be processed.
		 */
		static public LinkedList<Map <String, String>> getItems() {
			synchronized(buffer) {
				LinkedList<Map <String, String>> result = buffer;
				buffer = new LinkedList<Map <String, String>>();
				return result;
			}
		}
	}
}