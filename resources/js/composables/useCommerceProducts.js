import { router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { centsToMajor, majorToCents, transformMoneyFields } from '@/utils/currency'

const sourceValue = (source) => {
    if (typeof source === 'function') return source()
    if (source && typeof source === 'object' && 'value' in source) return source.value

    return source
}

const defaultInventoryRow = (stock = 1) => ({
    country_code: 'DE',
    stock_quantity: stock,
    low_stock_threshold: 0,
    lead_time_days: 2,
    city: '',
    postal_code: '',
})

export function useCommerceProducts({
    clubs,
    marketplaceCategoryCommissions,
    pricingCountries,
    sellerApplication,
}) {
    const productCreateModal = ref(false)
    const websiteRequestModal = ref(false)
    const productAttributeRows = ref([{ name: '', values: [] }])
    const productVariantRows = ref([])
    const editProductModal = ref({ open: false, product: null })
    const deleteProductModal = ref({ open: false, product: null, confirmation: '' })

    const resolvedClubs = computed(() => sourceValue(clubs) || [])
    const resolvedCommissions = computed(() => sourceValue(marketplaceCategoryCommissions) || [])
    const resolvedPricingCountries = computed(() => sourceValue(pricingCountries) || [])
    const resolvedSellerApplication = computed(() => sourceValue(sellerApplication) || null)

    const productForm = useForm({
        club_id: '',
        title: '',
        description: '',
        attributes_text: '',
        attribute_options: [],
        variants: [],
        image_url: '',
        image_urls_text: '',
        image_upload: null,
        image_uploads: [],
        learning_course_id: '',
        offer_type: 'physical_product',
        category: 'equipment',
        product_type: 'single',
        sku: '',
        is_shippable: true,
        manages_stock: true,
        stock_quantity: 1,
        inventories: [defaultInventoryRow()],
        tax_class: 'standard',
        digital_delivery_note: '',
        course_outline_text: '',
        learning_goals_text: '',
        coaching_enabled: false,
        coach_feedback_instructions: '',
        price_cents: '',
    })
    const productImportForm = useForm({
        import_file: null,
    })
    const sellerApplicationForm = useForm({
        applicant_type: resolvedSellerApplication.value?.applicant_type || 'private',
        business_name: resolvedSellerApplication.value?.business_name || '',
        notes: resolvedSellerApplication.value?.notes || '',
        rule_product_truth: false,
        rule_rights: false,
        rule_shipping_returns: false,
        rule_commission: false,
        rule_data_privacy: false,
    })
    const editProductForm = useForm({
        title: '',
        description: '',
        image_url: '',
        image_upload: null,
        sku: '',
        price_cents: '',
        manages_stock: false,
        stock_quantity: '',
        inventories: [],
    })
    const websiteForm = useForm({
        club_id: '',
        domain: '',
        goals: '',
        notes: '',
    })

    const attributePresets = [
        { name: 'Farbe', values: ['Schwarz', 'Weiß', 'Rot', 'Blau', 'Grün', 'Gelb', 'Orange', 'Grau'] },
        { name: 'Größe', values: ['XS', 'S', 'M', 'L', 'XL', 'XXL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'] },
        { name: 'Material', values: ['Baumwolle', 'Polyester', 'Leder', 'Mesh', 'Kunststoff', 'Metall'] },
        { name: 'Dauer', values: ['30 Minuten', '60 Minuten', '90 Minuten', '1 Tag', '2 Tage', 'Wochenende'] },
        { name: 'Lieferart', values: ['E-Mail', 'Download', 'Online-Zugang', 'Vor Ort', 'Versand'] },
    ]

    const productStatusLabel = (status) => ({
        draft: 'Entwurf',
        review: 'In Prüfung',
        published: 'Online',
        rejected: 'Abgelehnt',
        archived: 'Archiviert',
    }[status] || status || 'Unbekannt')

    const sellerApplicationStatusLabel = (status) => ({
        pending: 'Wartet auf Prüfung',
        approved: 'Freigegeben',
        rejected: 'Abgelehnt',
    }[status] || 'Noch kein Antrag')

    const offerTypeLabel = (offerType, category) => ({
        physical_product: 'Produkt',
        online_course: 'Kurs',
        training_plan: 'Trainingsplan',
        camp: 'Camp',
        service: 'Service',
    }[offerType] || ({
        product: 'Produkt',
        course: 'Kurs',
        camp: 'Camp',
        service: 'Service',
        outfit_subscription: 'Outfit-Abo',
    }[category] || 'Angebot'))

    const isLearningOffer = computed(() => ['online_course', 'training_plan'].includes(productForm.offer_type))
    const productStockRequired = computed(() => productForm.offer_type === 'physical_product' && productForm.product_type !== 'digital')
    const productCommissionCategory = computed(() => productForm.category || 'product')
    const sellerMarketplaceCategories = computed(() => resolvedCommissions.value
        .filter((row) => !['service', 'outfit_subscription'].includes(row.category))
        .filter((row) => productForm.offer_type === 'physical_product'
            ? !['course', 'camp'].includes(row.category)
            : productForm.offer_type === 'camp'
                ? row.category === 'camp'
                : ['online_course', 'training_plan'].includes(productForm.offer_type)
                    ? row.category === 'course' || row.category === 'digital_products'
                    : false))
    const selectedProductCommission = computed(() => resolvedCommissions.value.find((row) => row.category === productCommissionCategory.value) || {
        category: productCommissionCategory.value,
        label: offerTypeLabel(productForm.offer_type, productForm.category),
        commission_percent: 10,
    })
    const productPricePreviewCents = computed(() => majorToCents(productForm.price_cents))
    const productCommissionPreviewCents = computed(() => Math.floor(productPricePreviewCents.value * (Number(selectedProductCommission.value.commission_percent || 0) / 100)))
    const productSellerPayoutPreviewCents = computed(() => Math.max(0, productPricePreviewCents.value - productCommissionPreviewCents.value))
    const inventoryCountries = computed(() => {
        const countries = resolvedPricingCountries.value.map((country) => country.country).filter(Boolean)
        return [...new Set(['DE', ...countries])]
    })
    const inventoryTotalStock = computed(() => normalizeInventoryRows(productForm.inventories).reduce((sum, row) => sum + Number(row.stock_quantity || 0), 0))

    watch(() => productForm.offer_type, (offerType) => {
        const map = {
            physical_product: [sellerMarketplaceCategories.value[0]?.category || 'equipment', 'single', true],
            online_course: ['course', 'digital', false],
            training_plan: ['course', 'digital', false],
            camp: ['camp', 'single', false],
            service: ['service', 'digital', false],
        }
        const [category, productType, shippable] = map[offerType] || map.physical_product
        productForm.category = category
        productForm.product_type = productType
        productForm.is_shippable = shippable
        if (offerType === 'physical_product' && productType !== 'digital') {
            productForm.manages_stock = true
            productForm.stock_quantity = productForm.stock_quantity || 1
            if (!productForm.inventories.length) {
                productForm.inventories = [defaultInventoryRow(productForm.stock_quantity || 1)]
            }
        }
        if (productType === 'digital') {
            productForm.manages_stock = false
            productForm.stock_quantity = ''
            productForm.inventories = []
        }
        if (offerType === 'training_plan') {
            productForm.coaching_enabled = true
        }
        if (offerType !== 'online_course') {
            productForm.learning_course_id = ''
        }

        const categories = sellerMarketplaceCategories.value
        if (categories.length && !categories.some((categoryItem) => categoryItem.category === productForm.category)) {
            productForm.category = categories[0].category
        }
    })

    watch(() => productForm.product_type, (productType) => {
        if (productForm.offer_type === 'physical_product' && productType !== 'digital') {
            productForm.manages_stock = true
            productForm.stock_quantity = productForm.stock_quantity || 1
            if (!productForm.inventories.length) {
                productForm.inventories = [defaultInventoryRow(productForm.stock_quantity || 1)]
            }
            return
        }

        productForm.manages_stock = false
        productForm.stock_quantity = ''
        productForm.inventories = []
    })

    const presetValuesFor = (name) => attributePresets.find((preset) => preset.name === name)?.values || []

    const normalizeAttributeRows = (rows) => rows
        .map((row) => ({
            name: String(row.name || '').trim(),
            values: Array.isArray(row.values)
                ? row.values.map((value) => String(value || '').trim()).filter(Boolean)
                : String(row.values || '').split(/[|,]/).map((value) => value.trim()).filter(Boolean),
        }))
        .filter((row) => row.name && row.values.length)

    const normalizeVariantRows = (rows) => rows
        .map((row) => ({
            sku: String(row.sku || '').trim(),
            price_cents: row.price_cents === '' || row.price_cents === null ? null : majorToCents(row.price_cents),
            stock_quantity: row.stock_quantity === '' || row.stock_quantity === null ? null : Number(row.stock_quantity),
            image_url: String(row.image_url || '').trim(),
            attributes: Object.entries(row.attributes || {})
                .map(([name, value]) => ({ name, value: String(value || '').trim() }))
                .filter((attribute) => attribute.name && attribute.value),
        }))
        .filter((row) => row.attributes.length)

    const normalizeInventoryRows = (rows = []) => rows
        .map((row) => ({
            country_code: String(row.country_code || '').toUpperCase().slice(0, 2),
            stock_quantity: Math.max(0, Number(row.stock_quantity || 0)),
            low_stock_threshold: Math.max(0, Number(row.low_stock_threshold || 0)),
            lead_time_days: row.lead_time_days === '' || row.lead_time_days === null ? '' : Math.max(0, Number(row.lead_time_days || 0)),
            city: row.city || '',
            postal_code: row.postal_code || '',
        }))
        .filter((row) => row.country_code.length === 2)

    const productAttributesText = () => productAttributeRows.value
        .map((row) => ({
            name: String(row.name || '').trim(),
            values: Array.isArray(row.values) ? row.values.join(' | ') : String(row.values || '').trim(),
        }))
        .filter((row) => row.name && row.values)
        .map((row) => `${row.name}: ${row.values}`)
        .join('\n')

    const addProductAttributeRow = () => {
        productAttributeRows.value.push({ name: '', values: [] })
    }

    const removeProductAttributeRow = (index) => {
        productAttributeRows.value.splice(index, 1)
        if (!productAttributeRows.value.length) {
            addProductAttributeRow()
        }
    }

    const addProductVariantRow = () => {
        productVariantRows.value.push({ sku: '', price_cents: productForm.price_cents || '', stock_quantity: '', image_url: '', attributes: {} })
    }

    const removeProductVariantRow = (index) => {
        productVariantRows.value.splice(index, 1)
    }

    const setProductImageUpload = (event) => {
        productForm.image_upload = event.target.files?.[0] || null
    }

    const setProductGalleryUploads = (event) => {
        productForm.image_uploads = Array.from(event.target.files || [])
    }

    const setProductImportFile = (event) => {
        productImportForm.import_file = event.target.files?.[0] || null
    }

    const addProductInventoryRow = () => {
        productForm.inventories.push(defaultInventoryRow(0))
    }

    const removeProductInventoryRow = (index) => {
        if (productForm.inventories.length <= 1) {
            return
        }

        productForm.inventories.splice(index, 1)
    }

    const addEditProductInventoryRow = () => {
        editProductForm.inventories.push(defaultInventoryRow(0))
    }

    const removeEditProductInventoryRow = (index) => {
        editProductForm.inventories.splice(index, 1)
    }

    const storeProduct = () => {
        productForm.attributes_text = productAttributesText()
        productForm.attribute_options = normalizeAttributeRows(productAttributeRows.value)
        productForm.variants = normalizeVariantRows(productVariantRows.value)
        productForm.inventories = productForm.manages_stock ? normalizeInventoryRows(productForm.inventories) : []
        if (productForm.manages_stock && productForm.inventories.length) {
            productForm.stock_quantity = productForm.inventories.reduce((sum, row) => sum + Number(row.stock_quantity || 0), 0) || productForm.stock_quantity || 1
        }

        productForm
            .transform((data) => transformMoneyFields(data, ['price_cents']))
            .post(route('auth.commerce.products.store'), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    productForm.reset('title', 'description', 'attributes_text', 'attribute_options', 'variants', 'image_url', 'image_urls_text', 'image_upload', 'image_uploads', 'learning_course_id', 'sku', 'digital_delivery_note', 'course_outline_text', 'learning_goals_text', 'coach_feedback_instructions')
                    productForm.offer_type = 'physical_product'
                    productForm.category = sellerMarketplaceCategories.value[0]?.category || 'equipment'
                    productForm.product_type = 'single'
                    productForm.is_shippable = true
                    productForm.manages_stock = true
                    productForm.stock_quantity = 1
                    productForm.inventories = [defaultInventoryRow()]
                    productForm.coaching_enabled = false
                    productAttributeRows.value = [{ name: '', values: [] }]
                    productVariantRows.value = []
                    productCreateModal.value = false
                },
            })
    }

    const importProducts = () => {
        productImportForm.post(route('auth.commerce.products.import'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => productImportForm.reset('import_file'),
        })
    }

    const storeSellerApplication = () => {
        sellerApplicationForm.post(route('auth.commerce.seller-application.store'), {
            preserveScroll: true,
        })
    }

    const openEditProductModal = (product) => {
        editProductForm.title = product.title || ''
        editProductForm.description = product.description || ''
        editProductForm.image_url = product.image_url || ''
        editProductForm.image_upload = null
        editProductForm.sku = product.sku || ''
        editProductForm.price_cents = centsToMajor(product.price_cents)
        editProductForm.manages_stock = Boolean(product.manages_stock)
        editProductForm.stock_quantity = product.stock_quantity ?? ''
        editProductForm.inventories = normalizeInventoryRows(product.inventories || []).map((inventory) => ({
            country_code: inventory.country_code,
            stock_quantity: inventory.stock_quantity,
            low_stock_threshold: inventory.low_stock_threshold || 0,
            lead_time_days: inventory.lead_time_days ?? '',
            city: inventory.warehouse?.city || '',
            postal_code: inventory.warehouse?.postal_code || '',
        }))
        editProductModal.value = { open: true, product }
    }

    const closeEditProductModal = () => {
        editProductModal.value = { open: false, product: null }
        editProductForm.clearErrors()
    }

    const setEditProductImageUpload = (file) => {
        editProductForm.image_upload = file
    }

    const submitEditProduct = () => {
        const product = editProductModal.value.product

        if (!product) {
            return
        }

        editProductForm.inventories = editProductForm.manages_stock ? normalizeInventoryRows(editProductForm.inventories) : []
        if (editProductForm.manages_stock && editProductForm.inventories.length) {
            editProductForm.stock_quantity = editProductForm.inventories.reduce((sum, row) => sum + Number(row.stock_quantity || 0), 0)
        }

        editProductForm
            .transform((data) => transformMoneyFields(data, ['price_cents']))
            .post(route('auth.commerce.my-products.update', product.id), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: closeEditProductModal,
            })
    }

    const updateOwnProductStatus = (product, status) => {
        router.put(route('auth.commerce.my-products.status.update', product.id), { status }, {
            preserveScroll: true,
        })
    }

    const openDeleteProductModal = (product) => {
        deleteProductModal.value = { open: true, product, confirmation: '' }
    }

    const closeDeleteProductModal = () => {
        deleteProductModal.value = { open: false, product: null, confirmation: '' }
    }

    const setDeleteProductConfirmation = (confirmation) => {
        deleteProductModal.value.confirmation = confirmation
    }

    const destroyOwnProduct = () => {
        const product = deleteProductModal.value.product

        if (!product || deleteProductModal.value.confirmation !== 'delete') {
            return
        }

        router.delete(route('auth.commerce.my-products.destroy', product.id), {
            preserveScroll: true,
            onSuccess: closeDeleteProductModal,
        })
    }

    const storeWebsiteRequest = () => websiteForm.post(route('auth.commerce.website-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            websiteForm.reset('domain', 'goals', 'notes')
            websiteRequestModal.value = false
        },
    })

    const openWebsiteRequestModal = () => {
        websiteForm.club_id ||= resolvedClubs.value[0]?.id || ''
        websiteRequestModal.value = true
    }

    return {
        addEditProductInventoryRow,
        addProductAttributeRow,
        addProductInventoryRow,
        addProductVariantRow,
        attributePresets,
        closeDeleteProductModal,
        closeEditProductModal,
        deleteProductModal,
        destroyOwnProduct,
        editProductForm,
        editProductModal,
        importProducts,
        inventoryCountries,
        inventoryTotalStock,
        isLearningOffer,
        normalizeAttributeRows,
        offerTypeLabel,
        openDeleteProductModal,
        openEditProductModal,
        openWebsiteRequestModal,
        presetValuesFor,
        productAttributeRows,
        productCommissionPreviewCents,
        productCreateModal,
        productForm,
        productImportForm,
        productSellerPayoutPreviewCents,
        productStatusLabel,
        productStockRequired,
        productVariantRows,
        removeEditProductInventoryRow,
        removeProductAttributeRow,
        removeProductInventoryRow,
        removeProductVariantRow,
        selectedProductCommission,
        sellerApplicationForm,
        sellerApplicationStatusLabel,
        sellerMarketplaceCategories,
        setDeleteProductConfirmation,
        setEditProductImageUpload,
        setProductGalleryUploads,
        setProductImageUpload,
        setProductImportFile,
        storeProduct,
        storeSellerApplication,
        storeWebsiteRequest,
        submitEditProduct,
        updateOwnProductStatus,
        websiteForm,
        websiteRequestModal,
    }
}

