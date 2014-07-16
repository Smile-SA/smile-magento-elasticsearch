/**
 * ElaticSearch tracking indexer plugin. Event processor abstract.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
package eu.smile.es.tracking.collect;
import java.util.Map;
import org.elasticsearch.common.logging.ESLogger;
import org.elasticsearch.common.logging.Loggers;

/**
 * Event processor.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
abstract public class Processor {
	
	/**
	 * Event processor logger 
	 */
	protected static final ESLogger logger = Loggers.getLogger(Processor.class);
	
	/**
	 * Indexer the processor is associated with.
	 */
	protected Indexer indexer;
	
	/**
	 * Settings of the processor
	 */
	protected Map <String, Object> settings;
	
	/**
	 * Associate with an indexer.
	 * 
	 * @param Indexer the processor is associated with.
	 * 
	 * @return Self reference.
	 */
	public Processor setIndexer(Indexer indexer) {
		this.indexer = indexer;
		return this;
	}
	
	/**
	 * Apply new settings for the processor.
	 * 
	 * @param settings New settings
	 * 
	 * @return Self reference.
	 */
	public Processor setSettings(Map <String, Object> settings)
	{
		this.settings = settings;
		return this;
	}
	
	/**
	 * Return name of the cuurent processor (used in logging)
	 * 
	 * @return Processor name
	 */
	public String getIndexName() {
		return (String) this.settings.get("index");
	}
	
	/**
	 * Process data for an event.
	 * 
	 * @param data Event data to be processed.
	 */
	public abstract void processData(Map <String, String> data);
}
