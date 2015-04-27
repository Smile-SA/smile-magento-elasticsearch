/**
 * ElaticSearch tracking indexer plugin.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
package eu.smile.es.tracking;
import java.util.Collection;
import org.elasticsearch.common.collect.Lists;
import org.elasticsearch.common.inject.Module;
import org.elasticsearch.plugins.AbstractPlugin;
import eu.smile.es.tracking.collect.CollectModule;

/**
 * ES plugin declaration.
 * 
 * @author Aurelien FOUCRET <aufou@smile.fr>
 */
public class TrackingIndexerPlugin extends AbstractPlugin {

	/**
	 * Returns the description of the plugin. 
	 */
	public String description() {
		return "Index data from log hit logs into an index";
	}

	/**
	 * Returns the name of the plugin.
	 */
	public String name() {
		return "eu.smile.es/tracking-indexer/1.0";
	}
	
	/**
	 * Returns the modules contained into the plugins.
	 */
	@Override
    public Collection<Class<? extends Module>> modules() {
        Collection<Class<? extends Module>> modules = Lists.newArrayList();
        modules.add(CollectModule.class);
        return modules;
    }
}
