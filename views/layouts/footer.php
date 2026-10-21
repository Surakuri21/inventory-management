<?php if (isLoggedIn()): ?>
            </div>
            <!-- /Page Content -->
        </div>
        <!-- /Main Content Area -->
    </div>
    <!-- /Wrapper -->

    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="px-6 py-4 flex justify-between items-center">
            <p class="text-sm text-gray-500">
                &copy; <?php echo date('Y'); ?> Inventory Management System. All rights reserved.
            </p>
            <?php if (isSessionExpiringSoon()): ?>
                <div id="session-warning" class="text-sm text-yellow-600 bg-yellow-50 px-3 py-1 rounded">
                    <i class="fas fa-clock mr-1"></i> Session expires soon!
                </div>
            <?php endif; ?>
        </div>
    </footer>
<?php else: ?>
    </div>
    <!-- /Guest wrapper -->
<?php endif; ?>

<!-- JavaScript dependencies -->
<script src="/assets/js/main.js"></script>
</body>
</html>