/**
 * ElaticSearch tracking indexer plugin. Indexation thread implementation.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
package eu.smile.es.tracking.collect;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;

import org.elasticsearch.action.admin.indices.settings.get.GetSettingsRequestBuilder;
import org.elasticsearch.action.admin.indices.settings.get.GetSettingsResponse;
import org.elasticsearch.action.bulk.BulkRequestBuilder;
import org.elasticsearch.action.bulk.BulkResponse;
import org.elasticsearch.client.Client;
import org.elasticsearch.common.hppc.cursors.ObjectObjectCursor;
import org.elasticsearch.common.logging.ESLogger;
import org.elasticsearch.common.logging.Loggers;
import org.elasticsearch.common.settings.Settings;
import org.elasticsearch.action.index.IndexRequest;
import org.elasticsearch.action.update.UpdateRequest;

import eu.smile.es.tracking.collect.CollectRestHandler;

/**
 * Thread in charge of indexing the event collected by the REST Handler
 * 
 * @author Aurelien FOUCRET <aufou@smile.fr>
 */
public class Indexer extends Thread {

	/**
	 * Time in millisecond between two thread executions
	 */
	final int THREAD_SLEEP_TIME = 10000;
	
	/**
	 * Logger for the class.
	 */
	private static final ESLogger logger = Loggers.getLogger(Indexer.class);
	
	/**
	 * ES Client used to process indexation.
	 */
	private Client client;
	
	/**
	 * List of the event processors used for events.
	 */
	private Map <String, List<Processor>> processors;
	
	/**
	 * Processor factory used to buld the processor list.
	 */
	private ProcessorFactory factory;
	
	/**
	 * Bulk request to be run at the end of each iteration.
	 */
	private BulkRequestBuilder bulkRequest;
	
	/**
	 * Thread init
	 * 
	 * @param client ES Client used to process indexation.
	 */
	public Indexer(Client client) {
		this.client = client;
		processors = new HashMap <String, List<Processor>>();
		factory = new ProcessorFactory(this);
	}
	
	/**
	 * Run the thread every THREAD_SLEEP_TIME ms.
	 * Index all pending content
	 */
	@Override
	public void run() {
		while(true) {
			LinkedList <Map <String, String>> items = CollectRestHandler.Buffer.getItems();
			processItems(items);
	        try {
				Thread.sleep(THREAD_SLEEP_TIME);
			} catch (InterruptedException e) {
				e.printStackTrace();
			}
		}
	}
	
	/**
	 * Reindex all items using processors.
	 * 
	 * @param items Items to be processed.
	 */
	private void processItems(LinkedList <Map <String, String>> items) {
		
		bulkRequest = client.prepareBulk();
		processors = new HashMap <String, List<Processor>>();
		
		for (Map <String, String> hit: items) {
			if (hit.containsKey("page.site_id")) {
				String siteId = hit.get("page.site_id");
				for (Processor processor: this.getProcessors(siteId)) {
					processor.processData(hit);
				}
			}
		}
		
		if (bulkRequest.numberOfActions() > 0) {
			logger.info("Tracking index bulk started (indexing " + bulkRequest.numberOfActions() + " items).");
			BulkResponse bulkResponse = bulkRequest.execute().actionGet();
			
			if (bulkResponse.hasFailures()) {
				logger.warn("There has been failure during indexing : " + bulkResponse.buildFailureMessage());
			}
		}
	}
	
	/**
	 * Append a request to the bulk to be exceuted at the end of the indexing iteration.
	 * 
	 * @param request
	 */
	public void addRequestToBulk(UpdateRequest request) {
		this.bulkRequest.add(request);
	}
	
	/**
	 * Append a request to the bulk to be exceuted at the end of the indexing iteration.
	 * 
	 * @param request
	 */
	public void addRequestToBulk(IndexRequest request) {
		this.bulkRequest.add(request);
	}
	
	/**
	 * Retrieve the list of all processors from indices config.
	 * 
	 * @param siteId SiteId we want the processor for.
	 * 
	 * @return List of processors.
	 */
	private List<Processor> getProcessors(String siteId) {
		
		if (!processors.containsKey(siteId)) {
			List <Processor> siteProcessors = new ArrayList <Processor>();
			//processors.put(siteId, siteProcessor);
			try {
				GetSettingsRequestBuilder settings = client.admin().indices().prepareGetSettings();
				GetSettingsResponse response = settings.execute().get();
				
				for (ObjectObjectCursor<String, Settings> settingEntry : response.getIndexToSettings()) {
					siteProcessors.addAll(addIndexProcessors(siteId, settingEntry.key, settingEntry.value));
				}				
				processors.put(siteId, siteProcessors);
			} catch (Exception e) {
				logger.error("Unable to sync index settings");
			}
		}
		
		return processors.get(siteId);
	}
	
	/**
	 * Append processors for a site using indices settings 
	 * 
	 * @param siteId        Current site id
	 * @param indexName     Current index name
	 * @param indexSettings Current index settings
	 * 
	 * @return List of all processors associated with the site
	 */
	private List <Processor> addIndexProcessors(String siteId, String indexName, Settings indexSettings)
	{
		List <Processor> indexProcessors = new ArrayList <Processor>();
		String indexTrackingId = indexSettings.get("index.tracking.site_id");
		if (indexTrackingId != null && indexTrackingId.compareTo(siteId) == 0) {
			Map <String, Object> processorsSettings = indexSettings.getByPrefix("index.tracking.processors.").getAsStructuredMap();
			for (Object processorSetting : processorsSettings.values()) {
				Processor processor = factory.createProcessor(indexName, (Map <String, Object>) processorSetting);
				if (processor != null) {
					indexProcessors.add(processor);
				}
			}
		}
		return indexProcessors;
	}
	
	/**
	 * Processor initialization
	 */
	public class ProcessorFactory {
		
		/**
		 * Indexer the factory is associated to. 
		 */
		Indexer indexer;
		
		/**
		 * Instanciate a new factory associated with an indexer.
		 * 
		 * @param indexer Indexer the factory is associated to.
		 */
		public ProcessorFactory(Indexer indexer) {
			this.indexer = indexer;
		}
		
		/**
		 * Create a new processor for an index.
		 * 
		 * @param indexName Current index name
		 * @param settings  Current index settings
		 * 
		 * @return The event processor
		 */
		public Processor createProcessor(String indexName, Map<String, Object> settings) {
			
			Processor processor = null;
			String className = (String) settings.get("class");
			
			if (!settings.containsKey("index")) {
				settings.put("index", indexName);
			}
			
			try {
				if (settings.containsKey("class")) {
					Class<?> processorClass = Class.forName(className);
					processor = (Processor) processorClass.newInstance();
					processor.setIndexer(indexer)
					         .setSettings(settings);
				}
			} catch (ClassNotFoundException e) {
				logger.error("Unable to create processor with classname " + className);
			} catch (InstantiationException e) {
				logger.error("Error during processor instantiation with classname " + className + "[ " + e.getClass().getName() + "]");
			} catch (IllegalAccessException e) {
				logger.error("Error during processor instantiation with classname " + className + "[ " + e.getClass().getName() + "]");
			}
			
			
			return processor;
		}
	}
}
